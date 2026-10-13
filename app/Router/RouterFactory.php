<?php

declare(strict_types=1);

namespace App\Router;

use Nette\Application\Routers\RouteList;

final class RouterFactory
{
  public static function createRouter(): RouteList
  {
    $router = new RouteList();
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
