<?php

declare(strict_types=1);

use Tester\Assert;

require dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/bin/booking-mail-worker.php';

$quote = [
  'arrival' => '2026-12-01',
  'departure' => '2026-12-04',
  'nights' => 3,
  'items' => [
    ['code' => 'first_night', 'quantity' => 1, 'unit_price_cents' => 12000, 'total_cents' => 12000, 'unit' => 'night'],
    ['code' => 'additional_night', 'quantity' => 2, 'unit_price_cents' => 11000, 'total_cents' => 22000, 'unit' => 'night'],
    ['code' => 'wellness', 'quantity' => 3, 'unit_price_cents' => 2000, 'total_cents' => 6000, 'unit' => 'night'],
    ['code' => 'frizzante', 'quantity' => 2, 'unit_price_cents' => 1000, 'total_cents' => 2000, 'unit' => 'item'],
  ],
  'accommodation_cents' => 34000,
  'extras_cents' => 8000,
  'total_cents' => 42000,
];
$base = [
  'booking_id' => 123,
  'event_type' => 'received',
  'recipient_type' => 'guest',
  'guest_name' => 'Jana Testová',
  'guest_email' => 'jana@example.test',
  'guest_phone' => null,
  'guest_note' => '',
  'arrival_date' => '2026-12-01',
  'departure_date' => '2026-12-04',
  'decision_reason' => null,
  'price_quote' => json_encode($quote, JSON_THROW_ON_ERROR),
  'price_total_cents' => 42000,
];
$message = bookingMailContent($base, 'info@example.test', '+421 900 000 000');
Assert::contains('R-000123', $message['subject']);
Assert::contains('Wellness (sauna a kúpacia kaďa): 3 × 20,00 € = 60,00 €', $message['body']);
Assert::contains('Tokajské Frizzante: 2 × 10,00 € = 20,00 €', $message['body']);
Assert::contains('Spolu: 420,00 €', $message['body']);
Assert::contains('Žiadosť ešte neznamená potvrdenie', $message['body']);
Assert::notContains('token', strtolower($message['body']));

$base['recipient_type'] = 'admin';
Assert::contains('Spolu: 420,00 €', bookingMailContent($base)['body']);

$base['price_quote'] = null;
$base['price_total_cents'] = null;
Assert::notContains('Spolu:', bookingMailContent($base)['body']);

$base['price_quote'] = json_encode($quote, JSON_THROW_ON_ERROR);
$base['price_total_cents'] = 100;
Assert::exception(static function () use ($base): void {
  bookingMailContent($base);
}, RuntimeException::class);
