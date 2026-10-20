<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Localization\SupportedLocales;
use App\Router\PublicRoutes;
use Tester\Assert;

Assert::same('sk', SupportedLocales::DEFAULT);
Assert::same(['sk', 'en'], SupportedLocales::CODES);

foreach (SupportedLocales::CODES as $locale) {
  Assert::true(SupportedLocales::isSupported($locale));
  SupportedLocales::assertSupported($locale);
}
Assert::false(SupportedLocales::isSupported('pl'));
Assert::exception(static fn () => SupportedLocales::assertSupported('pl'), InvalidArgumentException::class);
Assert::exception(static fn () => PublicRoutes::forLocale('pl'), InvalidArgumentException::class);
Assert::exception(static fn () => PublicRoutes::pathFor('gallery', 'pl'), InvalidArgumentException::class);
Assert::exception(static fn () => PublicRoutes::languageUrlsFor('unknown'), LogicException::class);

Assert::same('', PublicRoutes::pathFor('default', 'sk'));
Assert::same('en', PublicRoutes::pathFor('default', 'en'));
Assert::same('/galeria', PublicRoutes::forLocale('sk')['gallery']);
Assert::same('/en/gallery', PublicRoutes::forLocale('en')['gallery']);
Assert::same(['sk' => '/kontakt', 'en' => '/en/contact'], PublicRoutes::languageUrlsFor('contact'));

$paths = [];
foreach (PublicRoutes::PATHS as $action => $_) {
  foreach (SupportedLocales::CODES as $locale) {
    $url = PublicRoutes::pathFor($action, $locale);
    Assert::false(isset($paths[$url]));
    $paths[$url] = true;
    Assert::same('/' . $url, PublicRoutes::languageUrlsFor($action)[$locale]);
  }
}

$sql = file_get_contents(dirname(__DIR__, 2) . '/migrations/202610200001_localization_foundation.sql');
Assert::type('string', $sql);
foreach (['cms_page_translations', 'cms_media_translations', 'pricing_extra_translations'] as $table) {
  Assert::contains('DROP CONSTRAINT ' . $table . '_locale_check', $sql);
  Assert::contains('ADD CONSTRAINT ' . $table . '_locale_format', $sql);
}
