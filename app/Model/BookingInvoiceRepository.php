<?php

declare(strict_types=1);

namespace App\Model;

use App\Infrastructure\Database\ConnectionFactory;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use InvalidArgumentException;
use Nette\Database\Explorer;
use Throwable;

/**
 * Creates immutable paid-booking invoices with non-reusable yearly numbers.
 * The caller must authenticate the administrator.
 */
final class BookingInvoiceRepository
{
  private readonly Explorer $database;

  public function __construct(ConnectionFactory $connections, private readonly PricingRepository $pricing)
  {
    $this->database = $connections->createExplorer();
  }

  /**
   * @return array{name: string, address: string, ico: string, dic: string}|array{}
   */
  public function supplierSettings(): array
  {
    $row = $this->database->query(
      'SELECT name, address, ico, dic FROM booking_invoice_supplier_settings WHERE id = 1',
    )->fetch();

    if ($row === null) {
      return [];
    }

    return [
      'name' => (string) $row['name'],
      'address' => (string) $row['address'],
      'ico' => (string) $row['ico'],
      'dic' => (string) ($row['dic'] ?? ''),
    ];
  }

  /**
   * Updates supplier billing identity used for future invoices only.
   * Already issued invoice snapshots remain unchanged.
   *
   * @param array<string, mixed> $values
   */
  public function saveSupplierSettings(array $values): void
  {
    $name = self::required($values, 'supplier_name', 200);
    $address = self::required($values, 'supplier_address', 500);
    $ico = preg_replace('/\s+/', '', self::required($values, 'supplier_ico', 16));
    $dic = self::optional($values, 'supplier_dic', 16);

    if ($ico === null || preg_match('/^[0-9]{8}$/D', $ico) !== 1) {
      throw new InvalidArgumentException('IČO dodávateľa musí mať osem číslic.');
    }

    $this->database->query(
      'INSERT INTO booking_invoice_supplier_settings'
      . ' (id, name, address, ico, dic, updated_at)'
      . ' VALUES (1, ?, ?, ?, ?, CURRENT_TIMESTAMP)'
      . ' ON CONFLICT (id) DO UPDATE SET name = EXCLUDED.name,'
      . ' address = EXCLUDED.address, ico = EXCLUDED.ico,'
      . ' dic = EXCLUDED.dic,'
      . ' updated_at = CURRENT_TIMESTAMP',
      $name,
      $address,
      $ico,
      $dic === '' ? null : $dic,
    );
  }

  /**
   * @return array{number: string, document: array<string, mixed>}|null
   */
  public function forBooking(int $bookingId): ?array
  {
    $row = $this->database->query(
      'SELECT invoice_number, document FROM booking_invoices WHERE booking_id = ?',
      $bookingId,
    )->fetch();

    if ($row === null) {
      return null;
    }

    $document = is_string($row['document'])
      ? json_decode($row['document'], true, 512, JSON_THROW_ON_ERROR)
      : $row['document'];

    if (!is_array($document)) {
      throw new DomainException('Uložená faktúra má neplatný formát.');
    }

    return ['number' => (string) $row['invoice_number'], 'document' => $document];
  }

  /**
   * Issues one invoice, locking the booking and a yearly counter transactionally.
   * An existing invoice is returned without modifying the document or number.
   *
   * @param array<string, mixed> $values Submitted billing details.
   * @return array{number: string, document: array<string, mixed>}
   */
  public function issue(int $bookingId, array $values): array
  {
    if ($bookingId < 1) {
      throw new InvalidArgumentException('Neplatné číslo rezervácie.');
    }

    $this->database->beginTransaction();

    try {
      $row = $this->database->query(
        'SELECT b.id, b.status, b.arrival_date, b.departure_date, b.price_quote, b.price_total_cents, b.guest_name, b.guest_billing_address,'
        . ' p.status AS payment_status, p.paid_at'
        . ' FROM booking_requests b'
        . ' INNER JOIN booking_payments p ON p.booking_id = b.id'
        . ' WHERE b.id = ? FOR UPDATE OF b, p',
        $bookingId,
      )->fetch();

      if ($row === null || $row['status'] !== 'confirmed' || $row['payment_status'] !== 'paid') {
        throw new DomainException('Faktúru možno vystaviť iba k potvrdenej a zaplatenej rezervácii.');
      }

      $existing = $this->forBooking($bookingId);

      if ($existing !== null) {
        $this->database->commit();
        return $existing;
      }

      $quote = (new BookingQuoteView())->parse($row['price_quote'], $row['price_total_cents']);

      if ($quote === null) {
        throw new DomainException('Rezervácia nemá uložený cenový rozpis. Faktúru nemožno vystaviť.');
      }

      $savedSupplier = $this->supplierSettings();

      if ($savedSupplier === []) {
        throw new DomainException('Najprv vyplňte fakturačné údaje dodávateľa v Platobných údajoch.');
      }

      $supplier = [
        'name' => $savedSupplier['name'],
        'address' => $savedSupplier['address'],
        'ico' => $savedSupplier['ico'],
        'dic' => $savedSupplier['dic'],
      ];

      $customer = [
        'name' => self::required([
          'value' => $values['customer_name'] ?? $row['guest_name'],
        ], 'value', 200),
        'address' => self::optional([
          'value' => $values['customer_address'] ?? $row['guest_billing_address'],
        ], 'value', 500),
        'ico' => self::optional($values, 'customer_ico', 16),
        'dic' => self::optional($values, 'customer_dic', 16),
      ];

      if (preg_match('/^[0-9]{8}$/D', preg_replace('/\s+/', '', $supplier['ico'])) !== 1) {
        throw new InvalidArgumentException('IČO dodávateľa musí mať osem číslic.');
      }

      $now = new DateTimeImmutable('now', new DateTimeZone('Europe/Bratislava'));
      $year = (int) $now->format('Y');

      $serial = $this->database->query(
        'INSERT INTO booking_invoice_numbers (year, last_number) VALUES (?, 1)'
        . ' ON CONFLICT (year) DO UPDATE SET last_number = booking_invoice_numbers.last_number + 1'
        . ' RETURNING last_number',
        $year,
      )->fetch();

      if ($serial === null) {
        throw new DomainException('Nepodarilo sa prideliť číslo faktúry.');
      }

      $number = sprintf('%04d-%02d-F%06d', $year, (int) $now->format('m'), (int) $serial['last_number']);
      $arrival = $row['arrival_date'] instanceof \DateTimeInterface
        ? $row['arrival_date']->format('Y-m-d')
        : (string) $row['arrival_date'];
      $departure = $row['departure_date'] instanceof \DateTimeInterface
        ? $row['departure_date']->format('Y-m-d')
        : (string) $row['departure_date'];
      $paidAt = $row['paid_at'];

      $guestCount = 2;
      $localTaxRate = $this->pricing->getPrices()['local_tax'];
      $invoiceLines = self::splitIncludedLocalTax($quote['lines'], $quote['nights'], $localTaxRate, $guestCount);

      $document = [
        'number' => $number,
        'issued_date' => $now->format('Y-m-d'),
        'payment_recorded_date' => $paidAt instanceof \DateTimeInterface
          ? $paidAt->format('Y-m-d') : substr((string) $paidAt, 0, 10),
        'arrival' => $arrival,
        'departure' => $departure,
        'supplier' => $supplier,
        'customer' => $customer,
        'lines' => $invoiceLines,
        'guest_count' => $guestCount,
        'local_tax_cents_per_guest_night' => $localTaxRate,
        'nights' => $quote['nights'],
        'total_cents' => $quote['total_cents'],
      ];

      $this->database->query(
        'INSERT INTO booking_invoices (booking_id, invoice_number, document) VALUES (?, ?, CAST(? AS jsonb))',
        $bookingId,
        $number,
        json_encode($document, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
      );

      $this->database->query(
        'INSERT INTO booking_audit_events (booking_id, event_type, detail) VALUES (?, ?, ?)',
        $bookingId,
        'invoice_issued',
        'Vystavená faktúra ' . $number,
      );

      $this->database->commit();
      return ['number' => $number, 'document' => $document];
    } catch (Throwable $error) {
      $this->database->rollBack();
      throw $error;
    }
  }

  /**
   * Reclassify the included guest tax without changing the booking total.
   * Accommodation rates already include this tax; extras remain unchanged.
   * Uses the stored quote, never the current accommodation rate.
   *
   * @param list<array<string, mixed>> $lines
   * @return list<array<string, mixed>>
   */
  public static function splitIncludedLocalTax(array $lines, int $nights, int $rateCents, int $guestCount = 2): array
  {
    if ($nights < 1 || $nights > 30 || $guestCount < 1 || $guestCount > 20 || $rateCents < 0) {
      throw new InvalidArgumentException('Neplatné údaje miestnej dane.');
    }

    $perNightTax = $rateCents * $guestCount;
    $accommodationNights = 0;
    $result = [];
    $originalTotal = 0;

    foreach ($lines as $line) {
      $code = $line['code'] ?? null;
      $quantity = $line['quantity'] ?? null;
      $unit = $line['unit_price_cents'] ?? null;
      $total = $line['total_cents'] ?? null;

      if (!is_int($quantity) || $quantity < 1 || !is_int($unit) || $unit < 0
        || !is_int($total) || $total !== $quantity * $unit) {
        throw new DomainException('Neplatná položka faktúry.');
      }

      $originalTotal += $total;
      if (in_array($code, ['first_night', 'additional_night'], true)) {
        if ($unit < $perNightTax) {
          throw new DomainException('Miestna daň prevyšuje cenu ubytovania za noc.');
        }

        $accommodationNights += $quantity;
        $line['unit_price_cents'] = $unit - $perNightTax;
        $line['total_cents'] = $quantity * ($unit - $perNightTax);
      }

      $result[] = $line;
    }

    if ($accommodationNights !== $nights) {
      throw new DomainException('Nesúlad počtu nocí v cenovom rozpise.');
    }

    if ($rateCents > 0) {
      $taxLine = [
        'code' => 'local_tax',
        'label' => 'Miestny poplatok',
        'quantity' => $nights * $guestCount,
        'unit_price_cents' => $rateCents,
        'total_cents' => $nights * $guestCount * $rateCents,
        'unit' => 'guest_night',
        'extra' => false,
        'guest_count' => $guestCount,
        'night_count' => $nights,
      ];

      $lastLodgingIndex = 0;
      foreach ($result as $index => $line) {
        if (in_array($line['code'] ?? null, ['first_night', 'additional_night'], true)) {
          $lastLodgingIndex = $index;
        }
      }
      array_splice($result, $lastLodgingIndex + 1, 0, [$taxLine]);
    }

    $newTotal = array_sum(array_column($result, 'total_cents'));
    if ($newTotal !== $originalTotal) {
      throw new DomainException('Rozpis miestnej dane zmenil celkovú cenu.');
    }

    return $result;
  }

  /**
   * @param array<string, mixed> $values
   */
  private static function required(array $values, string $key, int $limit): string
  {
    $value = self::optional($values, $key, $limit);

    if ($value === '') {
      throw new InvalidArgumentException('Chýbajú povinné fakturačné údaje.');
    }

    return $value;
  }

  /**
   * @param array<string, mixed> $values
   */
  private static function optional(array $values, string $key, int $limit): string
  {
    $value = trim((string) ($values[$key] ?? ''));

    if (mb_strlen($value) > $limit || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value)) {
      throw new InvalidArgumentException('Neplatné fakturačné údaje.');
    }

    return $value;
  }
}
