<?php

declare(strict_types=1);

namespace App\Model;

use App\Infrastructure\Database\ConnectionFactory;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use DomainException;
use InvalidArgumentException;
use Nette\Database\Explorer;

/**
 * Persistent manual bank-transfer settings and payment reconciliation.
 * Administrative authorization must be enforced by the calling presenter.
 */
final class BookingPaymentRepository
{
  private readonly Explorer $database;

  /**
   * @param ConnectionFactory $connections Project database connection.
   */
  public function __construct(ConnectionFactory $connections)
  {
    $this->database = $connections->createExplorer();
  }

  /**
   * Loads the account settings; missing bank details are not invented.
   *
   * @return array{iban: string|null, account_holder: string|null, bic: string|null}
   */
  public function settings(): array
  {
    $row = $this->database->query(
      'SELECT iban, account_holder, bic FROM booking_payment_settings WHERE id = 1',
    )->fetch();
    return [
      'iban' => $row === null ? null : ($row['iban'] === null ? null : (string) $row['iban']),
      'account_holder' => $row === null ? null : ($row['account_holder'] === null ? null : (string) $row['account_holder']),
      'bic' => $row === null ? null : ($row['bic'] === null ? null : (string) $row['bic']),
    ];
  }

  /**
   * Changes payment settings only after the caller authenticates an administrator.
   * Values are bound parameters, never concatenated into SQL.
   *
   * @param string $iban SEPA bank account.
   * @param string $accountHolder Name of payment recipient.
   * @param string $bic Optional bank identifier.
   * @return void
   */
  public function saveSettings(string $iban, string $accountHolder, string $bic = ''): void
  {
    $normalizedIban = BookingPaymentDetails::normalizeIban($iban);
    $holder = trim($accountHolder);
    $bic = strtoupper(trim($bic));
    if ($holder === '' || mb_strlen($holder) > 160) {
      throw new InvalidArgumentException('Zadajte príjemcu platby (max. 160 znakov).');
    }
    if ($bic !== '' && preg_match('/^[A-Z0-9]{4}[A-Z]{2}[A-Z0-9]{2}([A-Z0-9]{3})?$/D', $bic) !== 1) {
      throw new InvalidArgumentException('BIC musí obsahovať 8 alebo 11 platných znakov.');
    }
    $this->database->query(
      'INSERT INTO booking_payment_settings (id, iban, account_holder, bic, updated_at)'
      . ' VALUES (1, ?, ?, ?, CURRENT_TIMESTAMP)'
      . ' ON CONFLICT (id) DO UPDATE SET iban = EXCLUDED.iban, account_holder = EXCLUDED.account_holder,'
      . ' bic = EXCLUDED.bic, updated_at = CURRENT_TIMESTAMP',
      $normalizedIban,
      $holder,
      $bic === '' ? null : $bic,
    );
  }

  /**
   * Returns booking payment status, defaulting to unpaid when no status was recorded.
   * Do not present an amount or payment instructions for legacy bookings with no quote.
   *
   * @param int $bookingId Booking to inspect.
   * @return array{status: string, paid_at: string|null, total_cents: int|null, variable_symbol: string}|null
   */
  public function forBooking(int $bookingId): ?array
  {
    $row = $this->database->query(
      'SELECT b.created_at, b.price_total_cents, p.status, p.paid_at'
      . ' FROM booking_requests b LEFT JOIN booking_payments p ON p.booking_id = b.id'
      . ' WHERE b.id = ?',
      $bookingId,
    )->fetch();
    if ($row === null) {
      return null;
    }
    $createdAt = $row['created_at'];
    if ($createdAt instanceof DateTimeInterface) {
      $created = DateTimeImmutable::createFromInterface($createdAt);
    } else {
      $created = new DateTimeImmutable((string) $createdAt);
    }
    $createdYear = (int) $created->setTimezone(new DateTimeZone('Europe/Bratislava'))->format('Y');
    $variableSymbol = BookingPaymentDetails::variableSymbol($bookingId, $createdYear);
    $paidAt = $row['paid_at'];
    return [
      'status' => $row['status'] === null ? 'unpaid' : (string) $row['status'],
      'paid_at' => $paidAt === null ? null : ($paidAt instanceof \DateTimeInterface ? $paidAt->format(DATE_ATOM) : (string) $paidAt),
      'total_cents' => $row['price_total_cents'] === null ? null : (int) $row['price_total_cents'],
      'variable_symbol' => $variableSymbol,
    ];
  }

  /**
   * Changes recorded payment state, not the booking approval state.
   * Only confirmed bookings with an immutable total may be marked paid.
   * A later cancellation does not silently erase already received payments.
   *
   * @param int $bookingId Booking to update.
   * @param bool $paid True when owner has verified bank transfer manually.
   * @return void
   */
  public function setPaid(int $bookingId, bool $paid): void
  {
    if ($bookingId < 1) {
      throw new InvalidArgumentException('Neplatné ID rezervácie.');
    }
    $status = $paid ? 'paid' : 'unpaid';
    // Atomic conditional write: never create payment state for an unapproved stay.
    $written = $this->database->query(
      'INSERT INTO booking_payments (booking_id, status, paid_at, updated_at)'
      . ' SELECT id, ?, ' . ($paid ? 'CURRENT_TIMESTAMP' : 'NULL') . ', CURRENT_TIMESTAMP'
      . ' FROM booking_requests WHERE id = ? AND status = ? AND price_total_cents IS NOT NULL'
      . ' ON CONFLICT (booking_id) DO UPDATE SET status = EXCLUDED.status,'
      . ' paid_at = EXCLUDED.paid_at, updated_at = CURRENT_TIMESTAMP'
      . ' RETURNING booking_id',
      $status,
      $bookingId,
      'confirmed',
    )->fetch();
    if ($written === null) {
      throw new DomainException('Úhradu možno evidovať iba pri potvrdenej rezervácii so známou cenou.');
    }
  }

  /**
   * Returns an audit summary for successfully submitted payment instruction mail.
   *
   * @param int $bookingId Booking identifier.
   * @return array{sent_at: string|null, count: int}
   */
  public function instructionMailHistory(int $bookingId): array
  {
    $row = $this->database->query(
      'SELECT last_sent_at, send_count FROM booking_payment_mail_history WHERE booking_id = ?',
      $bookingId,
    )->fetch();
    if ($row === null) {
      return ['sent_at' => null, 'count' => 0];
    }
    $sent = $row['last_sent_at'];
    return [
      'sent_at' => $sent instanceof DateTimeInterface ? $sent->format('d.m.Y H:i') : (string) $sent,
      'count' => (int) $row['send_count'],
    ];
  }

  /**
   * Serializes sends for the same reservation and prevents accidental resends.
   * A resend is allowed after five minutes, when initiated by an administrator.
   * No successful send is recorded if the mail transport throws.
   *
   * @param int $bookingId Booking identifier.
   * @param callable(): void $send Already prepared SMTP action.
   * @return void
   */
  public function sendInstructions(int $bookingId, callable $send): void
  {
    if ($bookingId < 1) {
      throw new InvalidArgumentException('Neplatné ID rezervácie.');
    }
    $this->database->beginTransaction();
    try {
      // Row-level lock serializes competing requests, including a double click.
      $booking = $this->database->query(
        'SELECT b.id FROM booking_requests b'
        . ' LEFT JOIN booking_payments p ON p.booking_id = b.id'
        . ' WHERE b.id = ? AND b.status = ? AND b.price_total_cents > 0'
        . ' AND (p.status IS NULL OR p.status = ?) FOR UPDATE OF b',
        $bookingId,
        'confirmed',
        'unpaid',
      )->fetch();
      if ($booking === null) {
        throw new DomainException('Platobné pokyny možno poslať len k potvrdenej nezaplatenej rezervácii.');
      }
      $recent = $this->database->query(
        "SELECT last_sent_at > CURRENT_TIMESTAMP - INTERVAL '5 minutes' AS recent"
        . ' FROM booking_payment_mail_history WHERE booking_id = ?',
        $bookingId,
      )->fetch();
      if ($recent !== null && $recent['recent'] === true) {
        throw new DomainException('Platobné pokyny už boli nedávno odoslané. Ďalší pokus je možný po piatich minútach.');
      }
      $send();
      $this->database->query(
        'INSERT INTO booking_payment_mail_history (booking_id, last_sent_at, send_count)'
        . ' VALUES (?, CURRENT_TIMESTAMP, 1)'
        . ' ON CONFLICT (booking_id) DO UPDATE SET'
        . ' last_sent_at = EXCLUDED.last_sent_at,'
        . ' send_count = booking_payment_mail_history.send_count + 1',
        $bookingId,
      );
      $this->database->commit();
    } catch (\Throwable $error) {
      $this->database->rollBack();
      throw $error;
    }
  }
}
