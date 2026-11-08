<?php

declare(strict_types=1);

namespace App\Model;

use App\Infrastructure\Database\ConnectionFactory;
use DateTimeInterface;
use InvalidArgumentException;
use Nette\Database\Explorer;
use RuntimeException;

/**
 * Fetches active reservations overlapping the displayed calendar month.
 */
final class BookingCalendarRepository
{
  private readonly Explorer $database;

  private readonly BookingQuoteView $quoteView;

  /**
   * @param ConnectionFactory $connections Application database connection.
   */
  public function __construct(ConnectionFactory $connections)
  {
    $this->database = $connections->createExplorer();
    $this->quoteView = new BookingQuoteView();
  }

  /**
   * Returns the active stays overlapping [monthStart, nextMonthStart).
   * Rejected and cancelled reservations are deliberately excluded.
   *
   * @param string $monthStart First day of the month, in Y-m-d format.
   * @param string $nextMonthStart First day of the following month, in Y-m-d format.
   * @return list<array{id: int, guest: string, email: string, phone: string, note: string, start: string, end: string, status: string, paymentStatus: string, quoteExtras: list<array{code: string, label: string, quantity: int}>, quoteTotal: string|null, quoteState: string}>
   */
  public function activeForMonth(string $monthStart, string $nextMonthStart): array
  {
    $rows = $this->database->query(
      'SELECT b.id, b.guest_name, b.guest_email, b.guest_phone, b.guest_note,'
      . ' b.arrival_date, b.departure_date, b.status, b.price_quote, b.price_total_cents,'
      . ' p.status AS payment_status'
      . ' FROM booking_requests b'
      . ' LEFT JOIN booking_payments p ON p.booking_id = b.id'
      . ' WHERE b.status IN (?, ?) AND b.arrival_date < ? AND b.departure_date > ?'
      . ' ORDER BY b.arrival_date ASC, b.id ASC',
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

      $extras = [];
      $total = null;
      $quoteState = 'legacy';
      try {
        $quote = $this->quoteView->parse($row['price_quote'], $row['price_total_cents']);
        if ($quote !== null) {
          $quoteState = 'available';
          $total = BookingQuoteView::euros($quote['total_cents']);
          foreach ($quote['lines'] as $line) {
            if ($line['extra']) {
              $extras[] = [
                'code' => $line['code'],
                'label' => $line['label'],
                'quantity' => $line['quantity'],
              ];
            }
          }
        }
      } catch (InvalidArgumentException) {
        $quoteState = 'invalid';
      }

      $result[] = [
        'id' => (int) $row['id'],
        'guest' => (string) $row['guest_name'],
        'email' => (string) ($row['guest_email'] ?? ''),
        'phone' => (string) ($row['guest_phone'] ?? ''),
        'note' => (string) ($row['guest_note'] ?? ''),
        'start' => $start,
        'end' => $end,
        'status' => $status,
        'paymentStatus' => $status === 'confirmed' && $row['payment_status'] === 'paid' ? 'paid' : 'unpaid',
        'quoteExtras' => $extras,
        'quoteTotal' => $total,
        'quoteState' => $quoteState,
      ];
    }

    return $result;
  }
}
