<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Router\PublicRoutes;
use Nette\Neon\Neon;
use Tester\Assert;

$base = dirname(__DIR__, 2);
$sk = Neon::decodeFile($base . '/lang/messages.sk.neon');
$en = Neon::decodeFile($base . '/lang/messages.en.neon');
Assert::type('array', $sk);
Assert::type('array', $en);
foreach (array_merge(['@layout.latte'], array_map(static fn(string $action): string => 'Homepage/' . $action . '.latte', array_keys(PublicRoutes::PATHS))) as $filename) {
    $template = file_get_contents($base . '/app/Presenters/templates/' . $filename);
    Assert::type('string', $template);
    preg_match_all('/\{_\s*[\x27\x22](messages\.[^\x27\x22]+)[\x27\x22]\s*\}/', $template, $matches);
    foreach ($matches[1] as $identifier) {
        $key = substr($identifier, strlen('messages.'));
        Assert::true(isset($sk[$key]), "Missing SK: $filename : $identifier");
        Assert::true(isset($en[$key]), "Missing EN: $filename : $identifier");
    }
}
