<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use Contributte\Translation\Translator;
use Nette\Neon\Neon;
use Tester\Assert;

/** @return array<string, string> */
function readCatalog(string $locale): array
{
    $path = dirname(__DIR__, 2) . "/lang/messages.$locale.neon";
    $catalog = Neon::decodeFile($path);
    Assert::type('array', $catalog);

    foreach ($catalog as $key => $value) {
        Assert::type('string', $key);
        Assert::type('string', $value);
        Assert::true(trim($value) !== '', "Empty translation: $locale:$key");
    }

    /** @var array<string, string> $catalog */
    return $catalog;
}

$sk = readCatalog('sk');
$en = readCatalog('en');

$skKeys = array_keys($sk);
$enKeys = array_keys($en);
sort($skKeys);
sort($enKeys);
Assert::same($skKeys, $enKeys, 'SK and EN translation keys must match.');
Assert::true(count($skKeys) > 0, 'Translation catalog must not be empty.');

// Integration test: resolve translations through the real Nette DI container.
$configurator = require dirname(__DIR__, 2) . '/app/bootstrap.php';
$container = $configurator->createContainer();
/** @var Translator $translator */
$translator = $container->getByType(Translator::class);

foreach (['sk' => $sk, 'en' => $en] as $locale => $catalog) {
    $translator->setLocale($locale);

    foreach ($catalog as $key => $expected) {
        // Contributte uses the first dot to separate domain and message key.
        $actual = $translator->translate('messages.' . $key);
        Assert::same($expected, $actual, "Translation lookup failed: $locale:$key");
    }
}

// Catch typos and missing translations in the current public templates.
foreach (['@layout.latte', 'Homepage/default.latte'] as $filename) {
    $path = dirname(__DIR__, 2) . '/app/Presenters/templates/' . $filename;
    $template = file_get_contents($path);
    Assert::type('string', $template);

    preg_match_all('/\{_\s*[\x27\x22]([^\x27\x22]+)[\x27\x22]\s*\}/', $template, $matches);
    foreach ($matches[1] as $identifier) {
        Assert::true(str_starts_with($identifier, 'messages.'), "Missing messages domain in $filename: $identifier");
        $key = substr($identifier, strlen('messages.'));
        Assert::true(isset($sk[$key]), "Unknown translation key in $filename: $identifier");
    }
}
