<?php

declare(strict_types=1);

namespace App\Components;

use App\Infrastructure\Database\ConnectionFactory;
use DateTimeImmutable;
use DateTimeZone;
use Nette\Application\UI\Control;

/**
 * Displays the next confirmed arrivals on the authenticated administration dashboard.
 */
final class UpcomingArrivalsControl extends Control
{
  /**
   * @param ConnectionFactory $connections Database connection factory.
   */
  public function __construct(
    private readonly ConnectionFactory $connections,
  ) {
  }

  /**
   * Renders up to five confirmed stays, ordered by arrival date.
   *
   * @return void
   */
  public function render(): void
  {
    $today = (new DateTimeImmutable('today', new DateTimeZone('Europe/Bratislava')))
      ->format('Y-m-d');

    $rows = $this->connections->createExplorer()->query(
      'SELECT id, guest_name, arrival_date, departure_date'
      . ' FROM booking_requests'
      . ' WHERE status = ? AND arrival_date >= ?'
      . ' ORDER BY arrival_date ASC, id ASC LIMIT 5',
      'confirmed',
      $today,
    )->fetchAll();

    $arrivals = [];
    foreach ($rows as $row) {
      $arrivals[] = iterator_to_array($row);
    }

    $this->template->add('upcomingArrivals', $arrivals);
    $this->template->setFile(__DIR__ . '/templates/upcomingArrivals.latte');
    $this->template->render();
  }
}
