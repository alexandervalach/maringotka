<?php

declare(strict_types=1);

namespace App\Components;

use App\Infrastructure\Database\ConnectionFactory;
use Nette\Application\UI\Control;

/**
 * Shows pending booking requests in the authenticated administration dashboard.
 */
final class PendingBookingsControl extends Control
{
  /**
   * @param ConnectionFactory $connections Existing database connection factory.
   */
  public function __construct(
    private readonly ConnectionFactory $connections,
  ) {
  }

  /**
   * Reads a bounded list and renders the dashboard panel.
   *
   * @return void
   */
  public function render(): void
  {
    $database = $this->connections->createExplorer();
    $count = $database->query(
      'SELECT COUNT(*) AS total FROM booking_requests WHERE status = ?',
      'pending',
    )->fetch();
    $rows = $database->query(
      'SELECT id, guest_name, arrival_date, departure_date, created_at'
      . ' FROM booking_requests WHERE status = ? ORDER BY id DESC LIMIT 5',
      'pending',
    )->fetchAll();

    $requests = [];
    foreach ($rows as $row) {
      $requests[] = iterator_to_array($row);
    }

    $this->template->add('pendingCount', $count === null ? 0 : (int) $count['total']);
    $this->template->add('pendingRequests', $requests);
    $this->template->setFile(__DIR__ . '/templates/pendingBookings.latte');
    $this->template->render();
  }
}
