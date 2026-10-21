<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use Nette\Neon\Neon;
use Tester\Assert;

$root = dirname(__DIR__, 2);
$sk = Neon::decodeFile($root . '/lang/messages.sk.neon');
$en = Neon::decodeFile($root . '/lang/messages.en.neon');
foreach (['pricing.firstNight', 'pricing.additionalNight', 'pricing.wellness', 'pricing.breakfastTwo', 'pricing.night', 'media.hero'] as $key) {
    Assert::true(isset($sk[$key]), "Missing Slovak translation: $key");
    Assert::true(isset($en[$key]), "Missing English translation: $key");
}

$pricing = file_get_contents($root . '/app/Presenters/templates/Pricing/default.latte');
Assert::type('string', $pricing);

foreach (['first_night', 'additional_night', 'wellness', 'breakfast', 'romance'] as $key) {
  Assert::contains('$prices[\'' . $key . '\']', $pricing);
}

$gallery = file_get_contents($root . '/app/Presenters/templates/Gallery/default.latte');
Assert::type('string', $gallery);
Assert::contains('loading="lazy"', $gallery);
Assert::contains('srcset=', $gallery);
Assert::contains('$photos', $gallery);

$homepage = file_get_contents($root . '/app/Presenters/templates/Homepage/default.latte');
Assert::contains('fetchpriority="high"', $homepage);
foreach (['hero', 'interior', 'exterior-wide', 'exterior-portrait', 'lights', 'wellness'] as $slug) {
    Assert::true(is_file($root . '/www/assets/images/media/' . $slug . '-480.webp'));
}
