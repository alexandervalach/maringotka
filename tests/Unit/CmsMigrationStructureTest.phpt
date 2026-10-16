<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use Tester\Assert;

$file = dirname(__DIR__, 2) . '/migrations/202610130001_cms_foundation.sql';
$sql = file_get_contents($file);
Assert::type('string', $sql);

foreach (['cms_pages', 'cms_page_translations', 'cms_media', 'cms_media_translations', 'cms_gallery_items', 'pricing_settings', 'pricing_seasons', 'pricing_extras', 'pricing_extra_translations', 'public_settings'] as $table) {
    Assert::contains('CREATE TABLE ' . $table, $sql);
}
Assert::contains('pricing_seasons_no_overlap EXCLUDE USING gist', $sql);
Assert::contains("locale IN ('sk', 'en')", $sql);
Assert::contains('minimum_nights', $sql);

Assert::contains('first_night_price_cents', $sql);
Assert::contains('additional_night_price_cents', $sql);
Assert::contains('pricing_settings_prices_together', $sql);
Assert::false(str_contains($sql, 'base_nightly_price_cents'));
