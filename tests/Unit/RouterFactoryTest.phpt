<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Router\RouterFactory;
use Nette\Application\Routers\RouteList;
use Tester\Assert;

$router = RouterFactory::createRouter();
Assert::type(RouteList::class, $router);
$request = new Nette\Http\Request(
    new Nette\Http\UrlScript('http://localhost/')
);

$match = $router->match($request);

Assert::type('array', $match);
Assert::same('Homepage', $match['presenter']);
Assert::same('default', $match['action'] ?? 'default');