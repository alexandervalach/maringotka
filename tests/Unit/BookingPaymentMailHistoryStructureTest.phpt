<?php

declare(strict_types=1);

use Tester\Assert;

require dirname(__DIR__) . '/bootstrap.php';

// Structural checks complement integration testing; no live SMTP or QR API calls.
$sql = file_get_contents(dirname(__DIR__, 2) . '/migrations/202611100001_booking_payment_mail_history.sql');
Assert::type('string', $sql);
Assert::contains('PRIMARY KEY', $sql);
Assert::contains('REFERENCES booking_requests(id)', $sql);

// The presenter must submit through the repository's concurrency gate.
$presenter = file_get_contents(dirname(__DIR__, 2) . '/app/Presenters/BookingManagementPresenter.php');
Assert::type('string', $presenter);
Assert::contains('->sendInstructions(', $presenter);
