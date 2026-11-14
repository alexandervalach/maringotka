<?php

declare(strict_types=1);

use App\Components\Mail\BookingPaymentReceivedEmailTemplate;
use Tester\Assert;

require dirname(__DIR__) . '/bootstrap.php';

$event = [
  'booking_id' => 42,
  'guest_name' => '<script>example</script>',
  'arrival_date' => '2099-12-20',
  'departure_date' => '2099-12-22',
];
$content = BookingPaymentReceivedEmailTemplate::content($event, '2099-12-F000042', 12000);
Assert::contains('120,00 €', $content['body']);
Assert::contains('2099-12-F000042', $content['body']);
Assert::contains('&lt;script&gt;', $content['html']);
Assert::notContains('<script>', $content['html']);
Assert::exception(static function (): void {
  BookingPaymentReceivedEmailTemplate::validate("Invalid\nHeader", "Text");
}, InvalidArgumentException::class);
Assert::exception(static function (): void {
  BookingPaymentReceivedEmailTemplate::validate('Ok', '{{unknown}}');
}, InvalidArgumentException::class);

$defaults = BookingPaymentReceivedEmailTemplate::defaults();
Assert::contains('potvrdzujeme prijatie platby', $defaults['body']);
Assert::contains('{{invoice_number}}', $defaults['body']);
Assert::same('R-{{booking_id}} – Potvrdenie prijatia platby', $defaults['subject']);
