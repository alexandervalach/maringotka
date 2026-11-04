<?php

declare(strict_types=1);

namespace App\Model;

use App\Infrastructure\Database\ConnectionFactory;
use DateTimeInterface;
use Nette\Database\Explorer;
use RuntimeException;

/**
 * Fetches active reservations overlapping the displayed calendar month.
 */
final class BookingCalendarRepository
{
  private readonly Explorer $database;

  /**
   * @param ConnectionFactory $connections Application database connection.
   */
  public function __construct(ConnectionFactory $connections)
  {
    $this->database = $connections->createExplorer();
  }

  /**
   * Returns the active stays overlapping [monthStart, nextMonthStart).
   * Rejected and cancelled reservations are deliberately excluded.
   *
   * @param string $monthStart First day of the month, in Y-m-d format.
   * @param string $nextMonthStart First day of the following month, in Y-m-d format.
   * @return list<array{id: int, guest: string, start: string, end: string, status: string}>
   */
  public function activeForMonth(string $monthStart, string $nextMonthStart): array
  {
    $rows = $this->database->query(
      'SELECT id, guest_name, arrival_date, departure_date, status'
      . ' FROM booking_requests'
      . ' WHERE status IN (?, ?) AND arrival_date < ? AND departure_date > ?'
      . ' ORDER BY arrival_date ASC, id ASC',
      'pending',
      'confirmed',
      $nextMonthStart,
      $monthStart,
    )->fetchAll();

    $result = [];
    foreach ($rows as $row) {
      $arrival = $row['arrival_date'];
      $departure = $row['departure_date'];
      $start = $arrival instanceof DateTimeInterface ? $arrival->format('Y-m-d') : (string) $arrival;
      $end = $departure instanceof DateTimeInterface ? $departure->format('Y-m-d') : (string) $departure;
      $status = (string) $row['status'];

      if (!in_array($status, ['pending', 'confirmed'], true)) {
        throw new RuntimeException('Neočakávaný stav rezervácie.');
      }

      $result[] = [
        'id' => (int) $row['id'],
        'guest' => (string) $row['guest_name'],
        'start' => $start,
        'end' => $end,
        'status' => $status,
      ];
    }

    return $result;
  }
}
