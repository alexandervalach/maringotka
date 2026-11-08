<?php

declare(strict_types=1);

use App\Model\BookingCalendarMonth;
use Tester\Assert;

require __DIR__ . '/../../vendor/autoload.php';

$calendar = BookingCalendarMonth::build(new DateTimeImmutable('2026-11-01'), [
  ['id' => 1, 'guest' => 'Čakajúca', 'start' => '2026-11-02', 'end' => '2026-11-03', 'status' => 'pending'],
  ['id' => 2, 'guest' => 'Nezaplatená', 'start' => '2026-11-03', 'end' => '2026-11-04', 'status' => 'confirmed'],
  ['id' => 3, 'guest' => 'Zaplatená', 'start' => '2026-11-04', 'end' => '2026-11-05', 'status' => 'confirmed', 'paymentStatus' => 'paid'],
  ['id' => 4, 'guest' => 'Čakajúca s príznakom platby', 'start' => '2026-11-05', 'end' => '2026-11-06', 'status' => 'pending', 'paymentStatus' => 'paid'],
]);

$statuses = [];
foreach ($calendar['days'] as $day) {
  foreach ($day['items'] as $item) {
    $statuses[$item['id']] = [$item['status'], $item['paymentStatus']];
  }
}

Assert::same(['pending', 'unpaid'], $statuses[1]);
Assert::same(['confirmed', 'unpaid'], $statuses[2]);
Assert::same(['confirmed', 'paid'], $statuses[3]);
Assert::same(['pending', 'unpaid'], $statuses[4]);
Assert::same(2, $calendar['pending']);
Assert::same(2, $calendar['confirmed']);
