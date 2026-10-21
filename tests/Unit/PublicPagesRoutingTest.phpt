<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Localization\SupportedLocales;
use App\Router\PublicRoutes;
use App\Router\RouterFactory;
use Nette\Http\Request;
use Nette\Http\UrlScript;
use Tester\Assert;

$router = RouterFactory::createRouter();
foreach (PublicRoutes::PATHS as $action => $paths) {
  foreach (SupportedLocales::CODES as $locale) {
    $path = PublicRoutes::pathFor($action, $locale);
    $match = $router->match(new Request(new UrlScript('http://localhost/' . $path, '/index.php')));
    Assert::type('array', $match);
    $expectedPresenter = match ($action) {
      'contact' => 'Contact',
      'pricing' => 'Pricing',
      'gallery' => 'Gallery',
      default => 'Homepage',
    };
    $expectedAction = in_array($action, ['contact', 'pricing', 'gallery'], true) ? 'default' : $action;
    Assert::same($expectedPresenter, $match['presenter']);
    Assert::same($expectedAction, $match['action'] ?? 'default');
    Assert::same($locale, $match['locale']);
    Assert::same('/' . $path, PublicRoutes::forLocale($locale)[$action]);
    $filename = dirname(__DIR__, 2) . '/app/Presenters/templates/'
      . $expectedPresenter . '/' . $expectedAction . '.latte';
    Assert::true(is_file($filename), 'Missing template: ' . $filename);
  }
}
