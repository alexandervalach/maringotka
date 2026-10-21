<?php

declare(strict_types=1);

namespace App\Router;

use App\Localization\SupportedLocales;
use Nette\Application\Routers\RouteList;

final class RouterFactory
{
  public static function createRouter(): RouteList
  {
    $router = new RouteList();
    $router->addRoute('admin/login', ['presenter' => 'Admin', 'action' => 'login']);
    $router->addRoute('admin/home/<locale>', ['presenter' => 'Homepage', 'action' => 'edit']);
    $router->addRoute('admin/contact', ['presenter' => 'Contact', 'action' => 'edit']);
    $router->addRoute('admin/pricing', ['presenter' => 'Pricing', 'action' => 'edit']);
    $router->addRoute('admin', ['presenter' => 'Admin', 'action' => 'default']);
    foreach (PublicRoutes::PATHS as $action => $_) {
      foreach (SupportedLocales::CODES as $locale) {
        $router->addRoute(PublicRoutes::pathFor($action, $locale), [
          'presenter' => match ($action) {
            'contact' => 'Contact',
            'pricing' => 'Pricing',
            default => 'Homepage',
          },
          'action' => in_array($action, ['contact', 'pricing'], true) ? 'default' : $action,
          'locale' => $locale,
        ]);
      }
    }

    return $router;
  }
}
