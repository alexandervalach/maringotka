<?php

declare(strict_types=1);

use Tester\Assert;

require dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/bin/booking-mail-worker.php';

$source = file_get_contents(dirname(__DIR__, 2) . '/migrations/202610300001_booking_mail_outbox.sql');
Assert::type('string', $source);
Assert::contains('AFTER INSERT OR UPDATE OF status ON booking_requests', $source);
Assert::contains('ON CONFLICT (booking_id, event_type, recipient_type) DO NOTHING', $source);
Assert::contains('booking_mail_outbox_due_idx', $source);

$base = [
  'booking_id' => '42',
  'event_type' => 'received',
  'recipient_type' => 'guest',
  'guest_name' => 'Test Guest',
  'guest_email' => 'guest@example.test',
  'guest_phone' => null,
  'guest_note' => '',
  'arrival_date' => '2026-12-01',
  'departure_date' => '2026-12-03',
  'decision_reason' => null,
];
$result = bookingMailContent($base);
Assert::contains('R-000042', $result['subject']);
Assert::contains('čaká na schválenie', $result['body']);
Assert::notContains('token', mb_strtolower($result['body']));

$base['event_type'] = 'confirmed';
Assert::contains('potvrdená', bookingMailContent($base)['subject']);

$base['event_type'] = 'rejected';
$base['decision_reason'] = 'Termín nie je dostupný.';
Assert::contains('Termín nie je dostupný.', bookingMailContent($base)['body']);
$base['recipient_type'] = 'admin';
Assert::contains('guest@example.test', bookingMailContent($base)['body']);
