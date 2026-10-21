<?php

declare(strict_types=1);

namespace App\Localization;

use InvalidArgumentException;

/** 
 * Supported public and CMS content locales. 
 */
final class SupportedLocales
{
  public const DEFAULT = 'sk';

  /** @var list<string> */
  public const CODES = ['sk', 'en'];

  /** 
   * @param string $locale Locale identifier to check. 
   */
  public static function isSupported(string $locale): bool
  {
    return in_array($locale, self::CODES, true);
  }

  /**
   * @param string $locale Locale identifier to validate.
   * @throws InvalidArgumentException If the locale is not supported.
   */
  public static function assertSupported(string $locale): void
  {
    if (!self::isSupported($locale)) {
      throw new InvalidArgumentException('Unsupported locale: ' . $locale);
    }
  }
}
