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

    $calendarUntil = (new DateTimeImmutable('first day of this month'))->modify('+12 months');
    if ($start >= $calendarUntil || $end > $calendarUntil) {
      throw new InvalidArgumentException('Zvoľte termín v rámci nasledujúcich 12 kalendárnych mesiacov.');
    }

    $nights = (int) $start->diff($end)->format('%r%a');
    if ($nights < $minimumNights || $nights > 365) {
      throw new InvalidArgumentException(sprintf('Pobyt musí trvať od %d do 365 nocí.', $minimumNights));
    }
  }

  /**
   * Saves a pending request; concurrent overlaps are rejected by PostgreSQL.
   *
   * @param array{arrival_date: string, departure_date: string, guest_name: string, guest_email: string, guest_phone: string, guest_note: string} $values Validated guest input.
   * @param int $minimumNights Minimum stay from current prices.
   * @return string One-time cancellation token; only its hash is persisted.
   */
  public function request(array $values, int $minimumNights): string
  {
    self::validateStay($values['arrival_date'], $values['departure_date'], $minimumNights);
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
}
