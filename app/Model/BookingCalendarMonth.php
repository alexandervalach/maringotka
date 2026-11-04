<?php

declare(strict_types=1);

namespace App\Model;

use DateTimeImmutable;
use DatePeriod;
use DateInterval;
use InvalidArgumentException;

/**
 * Builds a Monday-first month grid using [arrival, departure) stays.
 */
final class BookingCalendarMonth
{
  /**
   * @param DateTimeImmutable $month First day of the requested month.
   * @param list<array{id: int, guest: string, email?: string, phone?: string, note?: string, start: string, end: string, status: string}> $reservations Active stays.
   * @return array{days: list<array{date: string, day: int, inMonth: bool, today: bool, items: list<array{id: int, guest: string, email: string, phone: string, note: string, start: string, end: string, status: string, arrival: bool}>}>, agenda: list<array{date: string, day: int, items: list<array{id: int, guest: string, email: string, phone: string, note: string, start: string, end: string, status: string, arrival: bool}>}>, pending: int, confirmed: int}
   */
  public static function build(DateTimeImmutable $month, array $reservations): array
  {
    if ($month->format('d') !== '01') {
      throw new InvalidArgumentException('Kalendár musí začínať prvým dňom mesiaca.');
    }

    $monthEnd = $month->modify('+1 month');
    $start = $month->modify('-' . ((int) $month->format('N') - 1) . ' days');
    $lastDay = $monthEnd->modify('-1 day');
    $end = $lastDay->modify('+' . (8 - (int) $lastDay->format('N')) . ' days');

    $days = [];
    $agenda = [];
    foreach (new DatePeriod($start, new DateInterval('P1D'), $end) as $date) {
      $key = $date->format('Y-m-d');
      $inMonth = $date->format('Y-m') === $month->format('Y-m');
      $items = [];
      if ($inMonth) {
        foreach ($reservations as $reservation) {
          if ($reservation['start'] <= $key && $key < $reservation['end']) {
            $items[] = [
              'id' => $reservation['id'],
              'guest' => $reservation['guest'],
              'email' => $reservation['email'] ?? '',
              'phone' => $reservation['phone'] ?? '',
              'note' => $reservation['note'] ?? '',
              'start' => $reservation['start'],
              'end' => $reservation['end'],
              'status' => $reservation['status'],
              'arrival' => $reservation['start'] === $key,
            ];
          }
        }
      }
      $day = [
        'date' => $key,
        'day' => (int) $date->format('j'),
        'inMonth' => $inMonth,
        'today' => $key === date('Y-m-d'),
        'items' => $items,
      ];
      $days[] = $day;
      if ($inMonth && $items !== []) {
        $agenda[] = ['date' => $key, 'day' => $day['day'], 'items' => $items];
      }
    }

    return [
      'days' => $days,
      'agenda' => $agenda,
      'pending' => count(array_filter($reservations, static fn (array $row): bool => $row['status'] === 'pending')),
      'confirmed' => count(array_filter($reservations, static fn (array $row): bool => $row['status'] === 'confirmed')),
    ];
  }
}
