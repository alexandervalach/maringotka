<?php

declare(strict_types=1);

namespace App\Presenters;

/**
 * Opens the administrator's last selected reservation overview.
 */
final class BookingOverviewPresenter extends BasePresenter
{
  /**
   * Requires administrator authentication before accessing preferences.
   *
   * @return void
   */
  protected function startup(): void
  {
    parent::startup();
    $this->requireAdmin();
  }

  /**
   * Redirects to the last selected view without exposing an extra page.
   *
   * @return void
   */
  public function actionDefault(): void
  {
    $view = $this->getSession('booking-admin-view');
    $month = $view->get('month');
    $url = $view->get('mode') === 'calendar'
      ? '/admin/booking-calendar' . (is_string($month) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $month) ? '?month=' . $month : '')
      : '/admin/bookings';
    $this->redirectUrl($url);
  }
}
