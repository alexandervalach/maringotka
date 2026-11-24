<?php

declare(strict_types=1);

namespace App\Model;

use App\Infrastructure\Database\ConnectionFactory;
use DateTimeImmutable;
use InvalidArgumentException;
use Nette\Database\DriverException;

/**
 * Creates reservation requests for the single accommodation.
 */
final class BookingRepository extends Repository
{
  /**
   * @param ConnectionFactory $connections Project database connection.
   */
  public function __construct(ConnectionFactory $connections)
  {
    parent::__construct($connections->createExplorer(), 'booking_requests');
  }

  /**
   * Checks whether the selected half-open stay interval is occupied.
   * This is advisory; the database exclusion constraint is authoritative.
   *
   * @param string $arrival ISO calendar date.
   * @param string $departure ISO calendar date.
   * @return bool Whether a blocking booking exists.
   */
  public function isOccupied(string $arrival, string $departure): bool
  {
    return $this->getTable()
      ->where('status', ['pending', 'confirmed'])
      ->where('arrival_date < ?', $departure)
      ->where('departure_date > ?', $arrival)
      ->limit(1)
      ->fetch() !== null;
  }

  /**
   * Returns blocked half-open date intervals for the public calendar.
   * Rejected and cancelled requests do not block dates.
   *
   * @param string $from Inclusive start date in YYYY-MM-DD format.
   * @param string $until Exclusive end date in YYYY-MM-DD format.
   * @return list<array{start: string, end: string}>
   */
  public function blockedIntervals(string $from, string $until): array
  {
    $rows = $this->getTable()
      ->where('status', ['pending', 'confirmed'])
      ->where('arrival_date < ?', $until)
      ->where('departure_date > ?', $from)
      ->order('arrival_date ASC')
      ->fetchAll();

    $intervals = [];
    foreach ($rows as $row) {
      $arrival = $row['arrival_date'];
      $departure = $row['departure_date'];
      $intervals[] = [
        'start' => $arrival instanceof \DateTimeInterface
          ? $arrival->format('Y-m-d')
          : (string) $arrival,
        'end' => $departure instanceof \DateTimeInterface
          ? $departure->format('Y-m-d')
          : (string) $departure,
      ];
    }
    return $intervals;
  }

  /**
   * Validates an entire stay without relying on browser-side constraints.
   *
   * @param string $arrival Arrival date in YYYY-MM-DD format.
   * @param string $departure Departure date in YYYY-MM-DD format.
   * @param int $minimumNights Minimum number of nights from pricing settings.
   * @return void
   */
  public static function validateStay(string $arrival, string $departure, int $minimumNights): void
  {
    $start = DateTimeImmutable::createFromFormat('!Y-m-d', $arrival);
    $end = DateTimeImmutable::createFromFormat('!Y-m-d', $departure);
    if ($start === false || $end === false || $start->format('Y-m-d') !== $arrival || $end->format('Y-m-d') !== $departure) {
      throw new InvalidArgumentException('Zadajte platné dátumy príchodu a odchodu.');
    }
    if ($start < new DateTimeImmutable('today')) {
      throw new InvalidArgumentException('Dátum príchodu nemôže byť v minulosti.');
    }
    // The public calendar displays twelve months, including the current month.
    // Checkout on the first day after that window is allowed.
    $calendarUntil = (new DateTimeImmutable('first day of this month'))->modify('+12 months');
    if ($start >= $calendarUntil || $end > $calendarUntil) {
      throw new InvalidArgumentException('Zvoľte termín v rámci nasledujúcich 12 kalendárnych mesiacov.');
    }
    $nights = (int) $start->diff($end)->format('%r%a');
    if ($nights < $minimumNights || $nights > BookingPriceCalculator::MAX_NIGHTS) {
      throw new InvalidArgumentException(sprintf('Pobyt musí trvať od %d do %d nocí.', $minimumNights, BookingPriceCalculator::MAX_NIGHTS));
    }
  }

  /**
   * Saves a pending request; concurrent overlaps are rejected by PostgreSQL.
   *
   * @param array{arrival_date: string, departure_date: string, guest_name: string, guest_email: string, guest_phone: string, guest_note: string} $values Validated guest input.
   * @param int $minimumNights Minimum stay from current prices.
   * @param array{arrival: string, departure: string, nights: int, items: list<array{code: string, quantity: int, unit_price_cents: int, total_cents: int, unit: string}>, accommodation_cents: int, extras_cents: int, total_cents: int} $quote Price snapshot calculated on the server from current rates.
   * @return string One-time cancellation token; only its hash is persisted.
   */
  public function request(array $values, int $minimumNights, array $quote): string
  {
    self::validateStay($values['arrival_date'], $values['departure_date'], $minimumNights);
   
    if ($quote['arrival'] !== $values['arrival_date'] || $quote['departure'] !== $values['departure_date']
      || $quote['total_cents'] < 0
      || $quote['accommodation_cents'] + $quote['extras_cents'] !== $quote['total_cents']) {
      throw new InvalidArgumentException('Neplatný cenový rozpis rezervácie.');
    }

    $computedItemsTotal = 0;

    foreach ($quote['items'] as $item) {
      if ($item['quantity'] < 1 || $item['unit_price_cents'] < 0
        || $item['total_cents'] !== $item['quantity'] * $item['unit_price_cents']) {
        throw new InvalidArgumentException('Neplatná položka cenového rozpisu.');
      }
      $computedItemsTotal += $item['total_cents'];
    }

    if ($computedItemsTotal !== $quote['total_cents']) {
      throw new InvalidArgumentException('Nesúhlasí celková cena rezervácie.');
    }

    $quoteJson = json_encode($quote, JSON_THROW_ON_ERROR);
    $name = trim($values['guest_name']);
    $email = trim($values['guest_email']);
    $phone = trim($values['guest_phone']);
    $note = trim($values['guest_note']);

    if ($name === '' || strlen($name) > 200 || !filter_var($email, FILTER_VALIDATE_EMAIL)
      || strlen($email) > 254 || strlen($phone) > 60 || strlen($note) > 5000) {
      throw new InvalidArgumentException('Skontrolujte kontaktné údaje.');
    }

    $token = bin2hex(random_bytes(32));
    
    try {
      $this->getTable()->insert([
        'arrival_date' => $values['arrival_date'],
        'departure_date' => $values['departure_date'],
        'guest_name' => $name,
        'guest_email' => $email,
        'guest_phone' => $phone === '' ? null : $phone,
        'guest_note' => $note,
        'status' => 'pending',
        'cancellation_token_hash' => hash('sha256', $token),
        'price_quote' => $quoteJson,
        'price_total_cents' => $quote['total_cents'],
      ]);
    } catch (DriverException $error) {
      // SQLSTATE 23P01: exclusion violation; the database prevents racing requests.
      if ((string) $error->getCode() === '23P01') {
        throw new BookingUnavailableException('Termín už nie je dostupný.', 0, $error);
      }
      throw $error;
    }
    return $token;
  }

  /**
   * Reserve a prepaid gift voucher and its stay atomically.
   * A voucher covers only the snapshotted nights and included services.
   * The database booking exclusion constraint remains authoritative.
   *
   * @param array{arrival_date: string, departure_date: string, guest_name: string, guest_email: string, guest_phone: string, guest_note: string} $values Guest details.
   * @param string $alias Public redemption code.
   * @return string Cancellation token; never disclose it on the page.
   */
  public function requestWithVoucher(array $values, string $alias): string
  {
    $alias = strtoupper(trim($alias));

    if (preg_match('/^[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{6}([23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{2})?$/D', $alias) !== 1) {
      throw new InvalidArgumentException('Neplatný formát darčekového kódu.');
    }

    $this->database->beginTransaction();

    try {
      // Lock by unique short_code before reading or updating usage state.
      $voucher = $this->database->query(
        'SELECT id, status, document, expires_at FROM gift_vouchers WHERE short_code = ? FOR UPDATE',
        $alias,
      )->fetch();

      if ($voucher === null || (string) $voucher['status'] !== 'active') {
        throw new InvalidArgumentException('Poukaz neexistuje alebo už bol použitý.');
      }

      $document = $voucher['document'];
      $document = is_string($document) ? json_decode($document, true, 512, JSON_THROW_ON_ERROR) : $document;

      if (!is_array($document)) {
        throw new InvalidArgumentException('Údaje poukazu sú neplatné.');
      }

      $nights = (int) ($document['nights'] ?? 0);

      if ($nights < 1 || $nights > BookingPriceCalculator::MAX_NIGHTS) {
        throw new InvalidArgumentException('Poukaz má neplatný počet nocí.');
      }

      self::validateStay($values['arrival_date'], $values['departure_date'], $nights);
      $start = new DateTimeImmutable($values['arrival_date']);
      $end = new DateTimeImmutable($values['departure_date']);

      if ((int) $start->diff($end)->format('%r%a') !== $nights) {
        throw new InvalidArgumentException('Zvolený termín nezodpovedá počtu nocí poukazu.');
      }

      $expiry = $voucher['expires_at'];
      $expiryDate = $expiry instanceof \DateTimeInterface ? $expiry->format('Y-m-d') : (string) $expiry;

      if ($values['arrival_date'] > $expiryDate) {
        throw new InvalidArgumentException('Poukaz nemôže byť použitý na pobyt po uplynutí platnosti.');
      }

      // Keep the ordinary quote format understood by the existing admin and e-mail views.
      $items = [[
        'code' => 'first_night', 'quantity' => 1,
        'unit_price_cents' => 0, 'total_cents' => 0, 'unit' => 'night',
      ]];

      if ($nights > 1) {
        $items[] = [
          'code' => 'additional_night', 'quantity' => $nights - 1,
          'unit_price_cents' => 0, 'total_cents' => 0, 'unit' => 'night',
        ];
      }

      if (($document['includes_wellness'] ?? false) === true) {
        $items[] = [
          'code' => 'wellness', 'quantity' => $nights,
          'unit_price_cents' => 0, 'total_cents' => 0, 'unit' => 'night',
        ];
      }

      if (($document['includes_wine'] ?? false) === true) {
        $items[] = [
          'code' => 'frizzante', 'quantity' => 1,
          'unit_price_cents' => 0, 'total_cents' => 0, 'unit' => 'item',
        ];
      }

      $quote = [
        'arrival' => $values['arrival_date'],
        'departure' => $values['departure_date'],
        'nights' => $nights,
        'items' => $items,
        'accommodation_cents' => 0,
        'extras_cents' => 0,
        'total_cents' => 0,
        'gift_voucher_id' => (int) $voucher['id'],
      ];

      $token = $this->request($values, $nights, $quote);
      $booking = $this->database->query(
        'SELECT id FROM booking_requests WHERE cancellation_token_hash = ?',
        hash('sha256', $token),
      )->fetch();

      if ($booking === null) {
        throw new \RuntimeException('Rezerváciu sa nepodarilo načítať.');
      }

      $this->database->query(
        'UPDATE gift_vouchers SET status = ?, redeemed_booking_id = ? WHERE id = ?',
        'redeemed',
        (int) $booking['id'],
        (int) $voucher['id'],
      );

      $this->database->commit();

      return $token;
    } catch (\Throwable $error) {
      $this->database->rollBack();
      throw $error;
    }
  }

}
