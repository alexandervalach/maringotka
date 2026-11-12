<?php

declare(strict_types=1);

use App\Helpers\BookingRequestEmailTemplate;
use Tester\Assert;

require dirname(__DIR__) . '/bootstrap.php';

$booking = [
  'booking_id' => 42,
  'guest_name' => '<script>alert(1)</script>',
  'arrival_date' => '2099-06-01',
  'departure_date' => '2099-06-03',
];
$quote = [
  '- Prva noc: 1 × 100,00 € = 100,00 €',
  '- Testovaci doplnok: 1 × 15,00 € = 15,00 €',
  'Spolu: 115,00 €',
];
$result = BookingRequestEmailTemplate::content($booking, $quote, 'kontakt@example.test');
Assert::contains('R-000042', $result['subject']);
Assert::contains('115,00 €', $result['body']);
Assert::contains('Testovaci doplnok', $result['body']);
Assert::contains('čaká na schválenie', $result['body']);
Assert::notContains('<script>', $result['html']);
Assert::contains('&lt;script&gt;', $result['html']);
Assert::notContains('IBAN', $result['body']);

$legacy = BookingRequestEmailTemplate::content($booking, [], 'kontakt@example.test');
Assert::contains('Cenový rozpis nie je dostupný.', $legacy['body']);

Assert::exception(static function (): void {
  BookingRequestEmailTemplate::validate('Predmet', 'Termín bez povinného rozpisu');
}, InvalidArgumentException::class);
Assert::exception(static function (): void {
  BookingRequestEmailTemplate::validate('Predmet', '{{booking_id}} {{arrival}} {{departure}} {{quote}} {{unrecognized}}');
}, InvalidArgumentException::class);
Assert::exception(static function (): void {
  BookingRequestEmailTemplate::validate("Test\r\nBcc: hacker@example.test", '{{booking_id}} {{arrival}} {{departure}} {{quote}}');
}, InvalidArgumentException::class);
