<?php

declare(strict_types=1);

use Tester\Assert;

require dirname(__DIR__) . '/bootstrap.php';
require dirname(__DIR__, 2) . '/bin/booking-mail-status.php';

$healthy = bookingMailStatusSummary([
  'due_count' => '2',
  'scheduled_count' => '3',
  'exhausted_count' => '0',
  'sent_count' => '15',
  'oldest_due_seconds' => '3599',
]);
Assert::same(2, $healthy['due']);
Assert::same(3, $healthy['scheduled']);
Assert::same(15, $healthy['sent']);
Assert::true($healthy['healthy']);

$stale = bookingMailStatusSummary([
  'due_count' => '1',
  'scheduled_count' => '0',
  'exhausted_count' => '0',
  'sent_count' => '0',
  'oldest_due_seconds' => '3600',
]);
Assert::false($stale['healthy']);

$exhausted = bookingMailStatusSummary([
  'due_count' => '0',
  'scheduled_count' => '0',
  'exhausted_count' => '1',
  'sent_count' => '0',
  'oldest_due_seconds' => null,
]);
Assert::false($exhausted['healthy']);
Assert::same(0, $exhausted['oldestDueSeconds']);

$empty = bookingMailStatusSummary([]);
Assert::true($empty['healthy']);
Assert::same(0, $empty['due']);
