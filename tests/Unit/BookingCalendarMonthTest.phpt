<?php

declare(strict_types=1);

use App\Model\BookingCalendarMonth;
use Tester\Assert;

require __DIR__ . '/../../vendor/autoload.php';

$month = new DateTimeImmutable('2026-11-01');
$stays = [
  ['id' => 1, 'guest' => 'Hosť A', 'start' => '2026-10-31', 'end' => '2026-11-03', 'status' => 'confirmed'],
  ['id' => 2, 'guest' => 'Hosť B', 'start' => '2026-11-03', 'end' => '2026-11-05', 'status' => 'pending'],
];
$result = BookingCalendarMonth::build($month, $stays);
Assert::same(1, $result['confirmed']);
Assert::same(1, $result['pending']);
Assert::same(42, count($result['days']));

$byDate = [];
foreach ($result['days'] as $day) {
  $byDate[$day['date']] = $day;
}
Assert::same('2026-10-26', $result['days'][0]['date']);
Assert::count(1, $byDate['2026-11-02']['items']);
Assert::same(1, $byDate['2026-11-02']['items'][0]['id']);
Assert::same(false, $byDate['2026-11-02']['items'][0]['arrival']);
Assert::count(1, $byDate['2026-11-03']['items']);
Assert::same(2, $byDate['2026-11-03']['items'][0]['id']);
Assert::same(true, $byDate['2026-11-03']['items'][0]['arrival']);
Assert::count(0, $byDate['2026-11-05']['items']);
Assert::count(0, $byDate['2026-10-31']['items']);

$feb = BookingCalendarMonth::build(new DateTimeImmutable('2028-02-01'), []);
Assert::same(35, count($feb['days']));
Assert::same([], $feb['agenda']);
