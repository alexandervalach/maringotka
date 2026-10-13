<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Router\PublicRoutes;
use App\Router\RouterFactory;
use Nette\Http\Request;
use Nette\Http\UrlScript;
use Tester\Assert;

$router = RouterFactory::createRouter();
foreach (PublicRoutes::PATHS as $action => $paths) {
    foreach (['sk', 'en'] as $locale) {
        $path = $paths[$locale];
        $url = new UrlScript('http://localhost/' . $path, '/index.php');
        $match = $router->match(new Request($url));
        
        Assert::type(
            'array',
            $match,
            sprintf('Routing failed for URL /%s (%s)', $path, $locale)
        );

        Assert::same('Homepage', $match['presenter']);
        Assert::same($action, $match['action'] ?? 'default');
        Assert::same($locale, $match['locale']);
        Assert::same('/' . $path, PublicRoutes::forLocale($locale)[$action]);
        $filename = dirname(__DIR__, 2) . '/app/Presenters/templates/Homepage/' . $action . '.latte';
        Assert::true(is_file($filename), 'Missing template: ' . $filename);
    }
}
