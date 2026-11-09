<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Localization\SupportedLocales;
use App\Router\PublicRoutes;
use Contributte\Translation\Translator;
use Nette\Application\UI\Presenter;

/** 
 * Shared presenter methods; public actions remain accessible anonymously. 
 * */
abstract class BasePresenter extends Presenter
{
  protected const ADMIN_SESSION_SECTION = 'maringotka_admin_v2';

  /**
   * Prepare common public locale and navigation context for localized pages.
   * Keep feature-specific title and content in individual presenters.
   *
   * @param Translator $translator Existing public translator.
   * @param mixed $locale Requested language parameter.
   * @param string $page PublicRoutes action key.
   * @return string Validated language code.
   */
  protected function configurePublicTemplate(Translator $translator, mixed $locale, string $page): string
  {
    if (!is_string($locale) || !SupportedLocales::isSupported($locale)) {
      $this->error('Unknown language.', 404);
    }

    $translator->setLocale($locale);
    $this->template->setTranslator($translator, $locale);
    $this->template->add('locale', $locale);
    $this->template->add('paths', PublicRoutes::forLocale($locale));
    $this->template->add('languageUrls', PublicRoutes::languageUrlsFor($page));

    return $locale;
  }

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
