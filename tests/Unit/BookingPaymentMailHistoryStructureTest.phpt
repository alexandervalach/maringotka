<?php

declare(strict_types=1);

use Tester\Assert;

require dirname(__DIR__) . '/bootstrap.php';

// Structural checks complement integration testing; no live SMTP or QR API calls.
$sql = file_get_contents(dirname(__DIR__, 2) . '/migrations/202611100001_booking_payment_mail_history.sql');
Assert::type('string', $sql);
Assert::contains('PRIMARY KEY', $sql);
Assert::contains('REFERENCES booking_requests(id)', $sql);

// Booking actions must not expose manual payment-instruction sending.
$presenter = file_get_contents(dirname(__DIR__, 2) . '/app/Presenters/BookingManagementPresenter.php');
Assert::type('string', $presenter);
Assert::notContains('createComponentPaymentEmailForm', $presenter);
Assert::notContains('createComponentQrForm', $presenter);

$template = file_get_contents(dirname(__DIR__, 2) . '/app/Presenters/templates/BookingManagement/edit.latte');
Assert::type('string', $template);
Assert::notContains('paymentEmailForm', $template);
Assert::notContains('qrForm', $template);

// Automatic booking confirmation/payment messages remain handled by the outbox worker.
$worker = file_get_contents(dirname(__DIR__, 2) . '/bin/booking-mail-worker.php');
Assert::type('string', $worker);
Assert::contains('$paidGuest', $worker);
Assert::contains('$confirmedGuest', $worker);
