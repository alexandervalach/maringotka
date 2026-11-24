<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Router\RouterFactory;
use Nette\Http\Request;
use Nette\Http\UrlScript;
use Tester\Assert;

$router = RouterFactory::createRouter();

foreach (['/rezervacia/overit-poukaz' => 'sk', '/en/booking/check-voucher' => 'en'] as $path => $locale) {
  $match = $router->match(new Request(new UrlScript('http://localhost' . $path, '/index.php')));
  Assert::type('array', $match);
  Assert::same('Booking', $match['presenter']);
  Assert::same('checkVoucher', $match['action']);
  Assert::same($locale, $match['locale']);
}

$root = dirname(__DIR__, 2);
$template = file_get_contents($root . '/app/Presenters/templates/Booking/default.latte');
$calendar = file_get_contents($root . '/www/assets/js/booking-calendar.js');
$voucher = file_get_contents($root . '/www/assets/js/booking-voucher.js');

Assert::contains('{input arrival_date}', $template);
Assert::contains('{input departure_date}', $template);
Assert::contains('{input voucher_code}', $template);
Assert::contains('data-voucher-status', $template);
Assert::contains('data-voucher-details', $template);
Assert::contains('!voucherNights() || nightsBetween(start, end) === voucherNights()', $calendar);
Assert::contains('voucherNights() ? addDays(iso, voucherNights())', $calendar);
Assert::contains('field.disabled = Boolean(voucher)', $voucher);
Assert::contains('form.dataset.voucherValid', $voucher);
