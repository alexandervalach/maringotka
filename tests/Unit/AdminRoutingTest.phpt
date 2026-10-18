<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Router\RouterFactory;
use Nette\Http\Request;
use Nette\Http\UrlScript;
use Tester\Assert;

$router = RouterFactory::createRouter();

foreach (['admin' => 'default', 'admin/login' => 'login'] as $path => $action) {
    $url = new UrlScript('http://localhost/' . $path, '/index.php');

    $match = $router->match(new Request($url));

    Assert::type('array', $match);
    Assert::same('Admin', $match['presenter']);
    Assert::same($action, $match['action']);
}

$url = new UrlScript('http://localhost/', '/index.php');

$match = $router->match(new Request($url));

Assert::type('array', $match);
Assert::same('Homepage', $match['presenter']);