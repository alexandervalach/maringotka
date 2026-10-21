<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Forms\ContactFormFactory;
use App\Model\ContactRepository;
use App\Model\Repository;
use App\Presenters\BasePresenter;
use App\Presenters\ContactPresenter;
use App\Router\PublicRoutes;
use App\Router\RouterFactory;
use Nette\Application\UI\Form;
use Nette\Http\Request;
use Nette\Http\UrlScript;
use Tester\Assert;

Assert::true(is_subclass_of(ContactPresenter::class, BasePresenter::class));
Assert::true(is_subclass_of(ContactRepository::class, Repository::class));
Assert::true(method_exists(ContactPresenter::class, 'actionEdit'));
Assert::true(method_exists(ContactPresenter::class, 'createComponentContactForm'));

$form = (new ContactFormFactory())->create();
Assert::type(Form::class, $form);
foreach (['phone', 'email', 'instagram_url', 'booking_url', 'maps_url', 'youtube_url', 'save'] as $field) {
  Assert::true(isset($form[$field]));
}
Assert::false(isset($form['published']));
Assert::same('button', (string) $form['save']->getControlPrototype()->class);

$defaults = ContactRepository::DEFAULTS;
Assert::same('+421 951 939 258', $defaults['phone']);
Assert::same('maringotkanazavadke@gmail.com', $defaults['email']);
Assert::same($defaults, ContactRepository::validateAndNormalize($defaults));
Assert::exception(
  static fn () => ContactRepository::validateAndNormalize([...$defaults, 'instagram_url' => 'javascript:alert(1)']),
  InvalidArgumentException::class,
);
Assert::exception(
  static fn () => ContactRepository::validateAndNormalize([...$defaults, 'booking_url' => 'http://example.com']),
  InvalidArgumentException::class,
);
Assert::exception(
  static fn () => ContactRepository::validateAndNormalize([...$defaults, 'email' => 'invalid']),
  InvalidArgumentException::class,
);
Assert::exception(
  static fn () => ContactRepository::validateAndNormalize([...$defaults, 'phone' => 'bad']),
  InvalidArgumentException::class,
);

Assert::same(['sk' => '/kontakt', 'en' => '/en/contact'], PublicRoutes::languageUrlsFor('contact'));
$router = RouterFactory::createRouter();
foreach (['/kontakt', '/en/contact'] as $path) {
  $url = new UrlScript('http://localhost' . $path, '/index.php');
  $match = $router->match(new Request($url));
  Assert::type('array', $match);
  Assert::same('Contact', $match['presenter']);
  Assert::same('default', $match['action']);
}
$url = new UrlScript('http://localhost/admin/contact', '/index.php');
$match = $router->match(new Request($url));
Assert::type('array', $match);
Assert::same('Contact', $match['presenter']);
Assert::same('edit', $match['action']);
