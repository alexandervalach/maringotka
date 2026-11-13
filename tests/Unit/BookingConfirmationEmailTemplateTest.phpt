<?php

declare(strict_types=1);

use App\Helpers\BookingConfirmationEmailTemplate;
use Tester\Assert;

require dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/bin/booking-mail-worker.php';

$booking = [
  'booking_id' => 42,
  'guest_name' => '<script>alert(1)</script>',
  'arrival_date' => '2099-06-01',
  'departure_date' => '2099-06-03',
];
$quote = [
  '- Prvá noc: 1 × 100,00 € = 100,00 €',
  'Spolu: 100,00 €',
];
$payment = BookingConfirmationEmailTemplate::paymentInstructions([
  'iban' => 'NEPLATNY_TESTOVACI_UCET',
  'account_holder' => 'Fiktivny prijemca',
  'bic' => null,
], 10000, '2099000042');
Assert::contains('2099000042', $payment);
Assert::contains('100.00 EUR', $payment);
Assert::notContains('BIC:', $payment);

$result = BookingConfirmationEmailTemplate::content($booking, $quote, 'kontakt@example.test', $payment);
Assert::contains('R-000042', $result['subject']);
Assert::contains('100,00 €', $result['body']);
Assert::contains('2099000042', $result['body']);
Assert::notContains('<script>', $result['html']);
Assert::contains('&lt;script&gt;', $result['html']);
Assert::notContains('Žiadosť ešte neznamená', $result['body']);

$missing = BookingConfirmationEmailTemplate::paymentInstructions(null, null, null);
Assert::contains('Zatiaľ platbu neposielajte.', $missing);
Assert::notContains('IBAN:', $missing);
Assert::contains('Cenový rozpis nie je dostupný.', BookingConfirmationEmailTemplate::content($booking, [], '', $missing)['body']);

Assert::exception(static function (): void {
  BookingConfirmationEmailTemplate::validate('Predmet', '{{booking_id}} {{arrival}} {{departure}} {{quote}}');
}, InvalidArgumentException::class);
Assert::exception(static function (): void {
  BookingConfirmationEmailTemplate::validate("Predmet\r\nBcc: test@example.test", '{{booking_id}} {{arrival}} {{departure}} {{quote}} {{payment_instructions}}');
}, InvalidArgumentException::class);
Assert::exception(static function (): void {
  BookingConfirmationEmailTemplate::validate('Predmet', '{{booking_id}} {{arrival}} {{departure}} {{quote}} {{payment_instructions}} {{unknown}}');
}, InvalidArgumentException::class);

// Confirmed guest messages use the editable template through the existing outbox worker.
$event = $booking + [
  'event_type' => 'confirmed',
  'recipient_type' => 'guest',
  'guest_email' => 'guest@example.test',
  'guest_phone' => null,
  'guest_note' => '',
  'price_quote' => null,
  'price_total_cents' => null,
];
$queued = bookingMailContent($event, 'kontakt@example.test', '', null, null, $payment);
Assert::contains('potvrdená', $queued['subject']);
Assert::contains('2099000042', $queued['body']);
Assert::contains('Cenový rozpis nie je dostupný.', $queued['body']);
Assert::notContains('Žiadosť ešte neznamená', $queued['body']);
