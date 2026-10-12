<?php

declare(strict_types=1);

namespace App\Router;

use Nette\Application\Routers\RouteList;

final class RouterFactory
{
    public static function createRouter(): RouteList
    {
        $router = new RouteList();
        $router->addRoute('en', ['presenter' => 'Homepage', 'action' => 'default', 'locale' => 'en']);
        $router->addRoute('', ['presenter' => 'Homepage', 'action' => 'default', 'locale' => 'sk']);
        return $router;
    }
}
