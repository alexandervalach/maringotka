<?php

declare(strict_types=1);

namespace App\Router;

use Nette\Application\Routers\RouteList;

final class RouterFactory
{
  public static function createRouter(): RouteList
  {
    $router = new RouteList();
    $router->addRoute('admin/login', ['presenter' => 'Admin', 'action' => 'login']);
    $router->addRoute('admin/home/<locale>', ['presenter' => 'Homepage', 'action' => 'edit']);
    $router->addRoute('admin', ['presenter' => 'Admin', 'action' => 'default']);
    foreach (PublicRoutes::PATHS as $action => $paths) {
      foreach (['en', 'sk'] as $locale) {
        $router->addRoute($paths[$locale], [
          'presenter' => 'Homepage',
          'action' => $action,
          'locale' => $locale,
        ]);
      }
    }

    return $router;
  }
}
