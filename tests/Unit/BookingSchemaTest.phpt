<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use Tester\Assert;

$file = dirname(__DIR__, 2) . '/migrations/202610250001_booking_requests.sql';
$sql = file_get_contents($file);
Assert::type('string', $sql);

Assert::contains('CREATE TABLE booking_requests', $sql);
Assert::contains('arrival_date < departure_date', $sql);
Assert::contains("daterange(arrival_date, departure_date, '[)') WITH &&", $sql);
Assert::contains("WHERE (status IN ('pending', 'confirmed'))", $sql);
Assert::contains('booking_requests_no_active_overlap EXCLUDE USING gist', $sql);
Assert::contains('cancellation_token_hash char(64) NOT NULL UNIQUE', $sql);
Assert::contains("status <> 'rejected' OR nullif(btrim(decision_reason), '') IS NOT NULL", $sql);
Assert::contains('created_at timestamptz NOT NULL DEFAULT now()', $sql);
Assert::contains('guest_email varchar(254) NOT NULL', $sql);
