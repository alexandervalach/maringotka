<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Model\BookingCalendarMonth;
use App\Model\BookingCalendarRepository;
use DateTimeImmutable;
use Nette\Application\BadRequestException;

/**
 * Shows active accommodation reservations in a protected monthly calendar.
 */
final class BookingCalendarPresenter extends BasePresenter
{
  /**
   * @param BookingCalendarRepository $bookings Calendar data access.
   */
  public function __construct(
    private readonly BookingCalendarRepository $bookings,
  ) {
    parent::__construct();
  }

  /**
   * Enforces administrator authentication before exposing guest data.
   *
   * @return void
   */
  protected function startup(): void
  {
    parent::startup();
    $this->requireAdmin();
  }

  /**
   * Renders the selected month, defaulting to the current month.
   *
   * @return void
   */
  public function renderDefault(): void
  {
    $raw = $this->getParameter('month');
    if ($raw !== null && (!is_string($raw) || !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $raw))) {
      throw new BadRequestException('Neplatný mesiac.', 400);
    }

    $month = $raw === null
      ? new DateTimeImmutable('first day of this month 00:00:00')
      : DateTimeImmutable::createFromFormat('!Y-m', $raw);
    if (!$month instanceof DateTimeImmutable || ($raw !== null && $month->format('Y-m') !== $raw)) {
      throw new BadRequestException('Neplatný mesiac.', 400);
    }

    $next = $month->modify('+1 month');
    $reservations = $this->bookings->activeForMonth($month->format('Y-m-d'), $next->format('Y-m-d'));
    $calendar = BookingCalendarMonth::build($month, $reservations);
    $monthNames = [
      1 => 'Január', 'Február', 'Marec', 'Apríl', 'Máj', 'Jún',
      'Júl', 'August', 'September', 'Október', 'November', 'December',
    ];

    $view = $this->getSession('booking-admin-view');
    $view->set('mode', 'calendar');
    $view->set('month', $month->format('Y-m'));

    $this->template->add('monthTitle', $monthNames[(int) $month->format('n')] . ' ' . $month->format('Y'));
    $this->template->add('previousMonth', $month->modify('-1 month')->format('Y-m'));
    $this->template->add('nextMonth', $next->format('Y-m'));
    $this->template->add('days', $calendar['days']);
    $this->template->add('agenda', $calendar['agenda']);
    $this->template->add('pendingCount', $calendar['pending']);
    $this->template->add('confirmedCount', $calendar['confirmed']);
  }
}
