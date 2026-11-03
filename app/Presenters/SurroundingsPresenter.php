<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Infrastructure\Database\ConnectionFactory;
use App\Localization\SupportedLocales;
use App\Router\PublicRoutes;
use Contributte\Translation\Translator;

/**
 * Public surroundings page with its own presenter and localized URLs.
 */
final class SurroundingsPresenter extends BasePresenter
{
  /**
   * @param Translator $translator Public SK/EN translations.
   * @param ConnectionFactory $connections Existing database connection factory.
   */
  public function __construct(
    private readonly Translator $translator,
    private readonly ConnectionFactory $connections,
  ) {
    parent::__construct();
  }

  /**
   * @param string $locale Requested public language.
   * @return void
   */
  public function actionDefault(string $locale): void
  {
    if (!SupportedLocales::isSupported($locale)) {
      $this->error('Unknown language.', 404);
    }
  }

  /**
   * Sets shared navigation and optional published CMS content.
   *
   * @return void
   */
  protected function beforeRender(): void
  {
    parent::beforeRender();
    $locale = $this->getParameter('locale');
    if (!is_string($locale) || !SupportedLocales::isSupported($locale)) {
      $this->error('Unknown language.', 404);
    }

    $this->translator->setLocale($locale);
    $this->template->setTranslator($this->translator, $locale);
    $this->template->add('locale', $locale);
    $this->template->add('paths', PublicRoutes::forLocale($locale));
    $this->template->add('languageUrls', PublicRoutes::languageUrlsFor('surroundings'));
    $this->template->add('title', $this->translator->translate('messages.navigation.surroundings'));
  }

  /**
   * Supplies optional published surroundings text without changing the existing CMS schema.
   *
   * @return void
   */
  public function renderDefault(): void
  {
    $locale = $this->getParameter('locale');
    if (!is_string($locale) || !SupportedLocales::isSupported($locale)) {
      $this->error('Unknown language.', 404);
    }

    $cmsTitle = null;
    $cmsSummary = null;
    $cmsBody = null;

    $database = $this->connections->createExplorer();
    $page = $database->table('cms_pages')
      ->where('slug', 'surroundings')
      ->where('is_published', true)
      ->fetch();

    if ($page !== null) {
      $translation = $database->table('cms_page_translations')
        ->where('page_id', $page['id'])
        ->where('locale', $locale)
        ->fetch();

      if ($translation !== null) {
        $cmsTitle = (string) $translation['title'];
        $cmsSummary = (string) $translation['summary'];
        $cmsBody = (string) $translation['body'];
      }
    }

    $this->template->add('cmsTitle', $cmsTitle);
    $this->template->add('cmsSummary', $cmsSummary);
    $this->template->add('cmsBody', $cmsBody);
  }
}
