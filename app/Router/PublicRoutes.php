<?php

declare(strict_types=1);

namespace App\Router;

use App\Localization\SupportedLocales;
use LogicException;

/** 
 * Localized public URLs. Every action must have a path for each supported locale. 
 */
final class PublicRoutes
{
  /** @var array<string, array<string, string>> */
  public const PATHS = [
    'default' => ['sk' => '', 'en' => 'en'],
    'gallery' => ['sk' => 'galeria', 'en' => 'en/gallery'],
    'surroundings' => ['sk' => 'okolie', 'en' => 'en/surroundings'],
    'pricing' => ['sk' => 'cennik', 'en' => 'en/pricing'],
    'voucher' => ['sk' => 'darcekovy-poukaz', 'en' => 'en/gift-voucher'],
    'contact' => ['sk' => 'kontakt', 'en' => 'en/contact'],
    'booking' => ['sk' => 'rezervacia', 'en' => 'en/booking'],
  ];

  /**
   * @param string $action Public presenter action.
   * @param string $locale Supported locale.
   * @return string Path without the leading slash for Nette routing.
   */
  public static function pathFor(string $action, string $locale): string
  {
    SupportedLocales::assertSupported($locale);
    if (!isset(self::PATHS[$action][$locale])) {
      throw new LogicException("Missing public route for {$action} in {$locale}.");
    }

    return self::PATHS[$action][$locale];
  }

  /**
   * @param string $locale Supported locale.
   * @return array<string, string> Public URLs keyed by action.
   */
  public static function forLocale(string $locale): array
  {
    SupportedLocales::assertSupported($locale);
    $urls = [];
    foreach (self::PATHS as $action => $_) {
      $urls[$action] = '/' . self::pathFor($action, $locale);
    }

    return $urls;
  }

  /**
   * @param string $action Current public presenter action.
   * @return array<string, string> Equivalent page URLs keyed by locale.
   */
  public static function languageUrlsFor(string $action): array
  {
    $urls = [];
    foreach (SupportedLocales::CODES as $locale) {
      $urls[$locale] = '/' . self::pathFor($action, $locale);
    }

    return $urls;
  }
}
