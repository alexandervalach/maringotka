<?php

declare(strict_types=1);

use Tester\Assert;

require dirname(__DIR__) . '/bootstrap.php';

/**
 * Checks that the booking audit migration registers transition-only history.
 */
$filename = dirname(__DIR__, 2) . '/migrations/202611010001_booking_status_history.sql';
$sql = file_get_contents($filename);
Assert::type('string', $sql);
Assert::contains('CREATE TABLE booking_status_history', $sql);
Assert::contains('REFERENCES booking_requests(id) ON DELETE RESTRICT', $sql);
Assert::contains('AFTER INSERT OR UPDATE OF status ON booking_requests', $sql);
Assert::contains('OLD.status IS DISTINCT FROM NEW.status', $sql);
Assert::contains('NEW.id, OLD.status, NEW.status, NEW.decision_reason', $sql);
Assert::contains('booking_status_history_booking_order_idx', $sql);
Assert::contains('SELECT id, NULL, status, decision_reason', $sql);
Assert::notContains('guest_email', $sql);
Assert::notContains('guest_phone', $sql);
Assert::notContains('guest_name', $sql);
