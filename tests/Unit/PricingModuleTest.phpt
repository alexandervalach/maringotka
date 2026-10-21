<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Forms\PricingFormFactory;
use App\Model\PricingRepository;
use App\Model\Repository;
use App\Presenters\BasePresenter;
use App\Presenters\PricingPresenter;
use App\Router\PublicRoutes;
use App\Router\RouterFactory;
use Nette\Application\UI\Form;
use Nette\Http\Request;
use Nette\Http\UrlScript;
use Tester\Assert;

Assert::true(is_subclass_of(PricingPresenter::class, BasePresenter::class));
Assert::true(is_subclass_of(PricingRepository::class, Repository::class));
Assert::true(method_exists(PricingPresenter::class, 'actionEdit'));
$form = (new PricingFormFactory())->create();
Assert::type(Form::class, $form);
foreach (['first_night', 'additional_night', 'wellness', 'breakfast', 'romance', 'minimum_nights', 'save'] as $field) {
  Assert::true(isset($form[$field]));
}
Assert::false(isset($form['published']));
Assert::same('button', (string) $form['save']->getControlPrototype()->class);
Assert::same([
  'first_night' => 12000,
  'additional_night' => 11000,
  'wellness' => 2000,
  'breakfast' => 2000,
  'romance' => 4050,
], PricingRepository::normalize([
  'first_night' => '120',
  'additional_night' => '110,00',
  'wellness' => '20.0',
  'breakfast' => '20',
  'romance' => '40,50',
]));
Assert::same('40,50', PricingRepository::formatAmount(4050));
Assert::same('20', PricingRepository::formatAmount(2000));
Assert::exception(
  static fn () => PricingRepository::normalize([
    'first_night' => '1e5', 'additional_night' => '110', 'wellness' => '20', 'breakfast' => '20', 'romance' => '40',
  ]),
  InvalidArgumentException::class,
);
$router = RouterFactory::createRouter();
foreach (['/cennik', '/en/pricing'] as $path) {
  $match = $router->match(new Request(new UrlScript('http://localhost' . $path, '/index.php')));
  Assert::type('array', $match);
  Assert::same('Pricing', $match['presenter']);
  Assert::same('default', $match['action']);
}
$match = $router->match(new Request(new UrlScript('http://localhost/admin/pricing', '/index.php')));
Assert::type('array', $match);
Assert::same('Pricing', $match['presenter']);
Assert::same('edit', $match['action']);
Assert::same(['sk' => '/cennik', 'en' => '/en/pricing'], PublicRoutes::languageUrlsFor('pricing'));
