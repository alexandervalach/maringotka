<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Router\RouterFactory;
use Nette\Http\Request;
use Nette\Http\UrlScript;
use Tester\Assert;

$router = RouterFactory::createRouter();
foreach (['/' => 'sk', '/en' => 'en'] as $path => $locale) {
    $match = $router->match(new Request(new UrlScript('http://localhost' . $path)));
    Assert::type('array', $match);
    Assert::same('Homepage', $match['presenter']);
    Assert::same('default', $match['action'] ?? 'default');
    Assert::same($locale, $match['locale']);
}
