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
$pricing = file_get_contents($root . '/app/Presenters/templates/Homepage/pricing.latte');
Assert::contains('120 €', $pricing);
Assert::contains('110 €', $pricing);
Assert::contains('20 €', $pricing);
Assert::contains('40 €', $pricing);
$gallery = file_get_contents($root . '/app/Presenters/templates/Homepage/gallery.latte');
Assert::contains('loading="lazy"', $gallery);
Assert::contains('srcset=', $gallery);
$homepage = file_get_contents($root . '/app/Presenters/templates/Homepage/default.latte');
Assert::contains('fetchpriority="high"', $homepage);
foreach (['hero', 'interior', 'exterior-wide', 'exterior-portrait', 'lights', 'wellness'] as $slug) {
    Assert::true(is_file($root . '/www/assets/images/media/' . $slug . '-480.webp'));
}
