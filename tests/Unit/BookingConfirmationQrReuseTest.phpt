<?php

declare(strict_types=1);

use Tester\Assert;

require dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/bin/booking-mail-worker.php';

// These cases must never access the external QR API.
Assert::null(bookingMailConfirmedQr(null, 10000, '2099000042'));
Assert::null(bookingMailConfirmedQr([
  'iban' => null,
  'account_holder' => null,
  'bic' => null,
], 10000, '2099000042'));
Assert::null(bookingMailConfirmedQr([
  'iban' => 'NEPLATNY_TESTOVACI_UCET',
  'account_holder' => 'Fiktivny prijemca',
  'bic' => null,
], null, '2099000042'));
Assert::null(bookingMailConfirmedQr([
  'iban' => 'NEPLATNY_TESTOVACI_UCET',
  'account_holder' => str_repeat('X', 71),
  'bic' => null,
], 10000, '2099000042'));
