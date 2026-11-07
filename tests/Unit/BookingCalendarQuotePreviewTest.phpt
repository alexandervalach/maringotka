<?php

declare(strict_types=1);

use App\Model\BookingCalendarMonth;
use Tester\Assert;

require __DIR__ . '/../../vendor/autoload.php';

$calendar = BookingCalendarMonth::build(new DateTimeImmutable('2026-11-01'), [[
  'id' => 12,
  'guest' => 'Testovací hosť',
  'start' => '2026-11-12',
  'end' => '2026-11-14',
  'status' => 'confirmed',
  'quoteExtras' => [
    ['code' => 'wellness', 'label' => 'Wellness', 'quantity' => 2],
    ['code' => 'frizzante', 'label' => 'Tokajské Frizzante', 'quantity' => 1],
  ],
  'quoteTotal' => '280,00 €',
  'quoteState' => 'available',
]]);

$arrival = null;
foreach ($calendar['days'] as $day) {
  if ($day['date'] === '2026-11-12') {
    $arrival = $day['items'][0];
    break;
  }
}
Assert::true($arrival !== null);
Assert::same('available', $arrival['quoteState']);
Assert::same('280,00 €', $arrival['quoteTotal']);
Assert::count(2, $arrival['quoteExtras']);
Assert::same(2, $arrival['nights']);
Assert::same(['code' => 'wellness', 'label' => 'Wellness', 'quantity' => 2], $arrival['quoteExtras'][0]);
Assert::true($arrival['arrival']);

$legacy = BookingCalendarMonth::build(new DateTimeImmutable('2026-11-01'), [[
  'id' => 13,
  'guest' => 'Starší hosť',
  'start' => '2026-11-15',
  'end' => '2026-11-16',
  'status' => 'pending',
]]);
foreach ($legacy['days'] as $day) {
  if ($day['date'] === '2026-11-15') {
    Assert::same('legacy', $day['items'][0]['quoteState']);
    Assert::same(null, $day['items'][0]['quoteTotal']);
    Assert::same([], $day['items'][0]['quoteExtras']);
    Assert::same(1, $day['items'][0]['nights']);
  }
}
