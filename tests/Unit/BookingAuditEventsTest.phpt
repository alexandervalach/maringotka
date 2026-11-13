<?php

declare(strict_types=1);

use Tester\Assert;

require dirname(__DIR__) . '/bootstrap.php';

// Migration and integration wiring checks; real DB/SMTP is tested in deployment.
$root = dirname(__DIR__, 2);
$migration = file_get_contents($root . '/migrations/202611130001_booking_audit_events.sql');
Assert::type('string', $migration);

Assert::contains('CREATE TABLE booking_audit_events', $migration);
Assert::contains('AFTER INSERT OR UPDATE OF status ON booking_payments', $migration);
Assert::contains('OLD.status IS DISTINCT FROM NEW.status', $migration);
Assert::contains('FROM booking_mail_outbox o', $migration);
Assert::contains('FROM booking_payment_mail_history', $migration);
Assert::contains('FROM booking_payments', $migration);
Assert::contains('ON CONFLICT (event_key) DO NOTHING', $migration);

// No private booking details are stored in the audit rows.
foreach (['guest_email', 'guest_name', 'guest_phone', 'iban', 'qr_payload', 'mail_body'] as $privateColumn) {
  Assert::notContains($privateColumn, $migration);
}

$payment = file_get_contents($root . '/app/Model/BookingPaymentRepository.php');
$worker = file_get_contents($root . '/bin/booking-mail-worker.php');
Assert::type('string', $payment);
Assert::type('string', $worker);
Assert::contains('booking_audit_events', $payment);
Assert::contains('booking_audit_events', $worker);
