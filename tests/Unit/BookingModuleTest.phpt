<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Forms\BookingFormFactory;
use App\Model\BookingRepository;
use App\Model\BookingUnavailableException;
use App\Model\Repository;
use App\Presenters\BasePresenter;
use App\Presenters\BookingPresenter;
use App\Router\PublicRoutes;
use App\Router\RouterFactory;
use Nette\Application\UI\Form;
use Nette\Http\Request;
use Nette\Http\UrlScript;
use Tester\Assert;

Assert::true(is_subclass_of(BookingPresenter::class, BasePresenter::class));
Assert::true(is_subclass_of(BookingRepository::class, Repository::class));
Assert::true(is_subclass_of(BookingUnavailableException::class, RuntimeException::class));
$form = (new BookingFormFactory())->create();
Assert::type(Form::class, $form);
foreach (['arrival_date', 'departure_date', 'guest_name', 'guest_email', 'guest_phone', 'guest_note', 'send'] as $field) {
  Assert::true(isset($form[$field]));
}
Assert::same('button', (string) $form['send']->getControlPrototype()->class);
$future = (new DateTimeImmutable('today'))->modify('+30 days');
$arrival = $future->format('Y-m-d');
$departure = $future->modify('+2 days')->format('Y-m-d');
BookingRepository::validateStay($arrival, $departure, 2);
Assert::exception(static fn () => BookingRepository::validateStay($arrival, $departure, 3), InvalidArgumentException::class);
Assert::exception(static fn () => BookingRepository::validateStay($departure, $arrival, 1), InvalidArgumentException::class);
Assert::exception(static fn () => BookingRepository::validateStay('2026-02-30', $departure, 1), InvalidArgumentException::class);
Assert::exception(static fn () => BookingRepository::validateStay('2020-01-01', '2020-01-02', 1), InvalidArgumentException::class);
$router = RouterFactory::createRouter();
foreach (['/rezervacia' => 'sk', '/en/booking' => 'en'] as $path => $locale) {
  $match = $router->match(new Request(new UrlScript('http://localhost' . $path, '/index.php')));
  Assert::type('array', $match);
  Assert::same('Booking', $match['presenter']);
  Assert::same('default', $match['action']);
  Assert::same($locale, $match['locale']);
}
Assert::same(['sk' => '/rezervacia', 'en' => '/en/booking'], PublicRoutes::languageUrlsFor('booking'));
