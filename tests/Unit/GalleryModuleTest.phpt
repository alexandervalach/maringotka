<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Forms\GalleryFormFactory;
use App\Model\GalleryCatalog;
use App\Model\GalleryRepository;
use App\Model\Repository;
use App\Presenters\BasePresenter;
use App\Presenters\GalleryPresenter;
use App\Router\RouterFactory;
use Nette\Application\UI\Form;
use Nette\Http\Request;
use Nette\Http\UrlScript;
use Tester\Assert;

Assert::true(is_subclass_of(GalleryRepository::class, Repository::class));
Assert::true(is_subclass_of(GalleryPresenter::class, BasePresenter::class));
$form = (new GalleryFormFactory())->create();
Assert::type(Form::class, $form);
foreach (['position', 'is_visible', 'alt_sk', 'alt_en', 'save'] as $name) {
  Assert::true(isset($form[$name]));
}
Assert::same(11, count(GalleryCatalog::items()));
$router = RouterFactory::createRouter();
foreach (['/galeria' => 'default', '/en/gallery' => 'default', '/admin/gallery' => 'manage', '/admin/gallery/15' => 'edit'] as $path => $action) {
  $match = $router->match(new Request(new UrlScript('http://localhost' . $path, '/index.php')));
  Assert::type('array', $match);
  Assert::same('Gallery', $match['presenter']);
  Assert::same($action, $match['action']);
}
