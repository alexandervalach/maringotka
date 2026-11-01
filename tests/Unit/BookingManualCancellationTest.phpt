<?php

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../bin/booking-mail-worker.php';

$row = [
  'booking_id' => 42,
  'guest_name' => 'Jana Testovacia',
  'arrival_date' => '2026-11-14',
  'departure_date' => '2026-11-16',
  'event_type' => 'cancelled',
  'recipient_type' => 'guest',
  'decision_reason' => 'Zrušené po telefonickej dohode.',
  'guest_email' => 'jana@example.test',
  'guest_phone' => null,
  'guest_note' => '',
];

$result = bookingMailContent($row, 'recepcia@example.test', '+421 900 000 000');
Assert::contains('R-000042', $result['subject']);
Assert::contains('Rezervácia bola zrušená', $result['body']);
Assert::contains('Zrušené po telefonickej dohode.', $result['body']);
Assert::contains('recepcia@example.test', $result['body']);
Assert::contains('+421 900 000 000', $result['body']);
Assert::notContains('token', $result['body']);

$row['event_type'] = 'received';
$result = bookingMailContent($row, 'recepcia@example.test');
Assert::contains('kontaktujte prevádzkovateľa', $result['body']);
Assert::contains('recepcia@example.test', $result['body']);
