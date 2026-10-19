<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette\Application\UI\Presenter;

/** 
 * Shared presenter methods; public actions remain accessible anonymously. 
 * */
abstract class BasePresenter extends Presenter
{
  protected const ADMIN_SESSION_SECTION = 'maringotka_admin_v2';

  /**
   * Protect only actions that mutate or expose administrator data.
   * The session name must match the existing Commit 09 login process.
   *
   * @return void
   */
  protected function requireAdmin(): void
  {
    $this->getSession()->start();
    $this->getHttpResponse()->setHeader('Cache-Control', 'no-store, private');
    $this->getHttpResponse()->setHeader('X-Robots-Tag', 'noindex, nofollow');

    if ($this->getSession()->getSection(self::ADMIN_SESSION_SECTION)->get('authenticated') !== true) {
      $this->redirect('Admin:login');
    }
  }
}
