<?php

declare(strict_types=1);

use App\Model\BookingCalendarMonth;
use Tester\Assert;

require __DIR__ . '/../../vendor/autoload.php';

$calendar = BookingCalendarMonth::build(new DateTimeImmutable('2026-11-01'), [[
  'id' => 12,
  'guest' => 'Testovací hosť',
  'email' => 'guest@example.test',
  'phone' => '+421123456789',
  'note' => 'Neskorý príchod',
  'start' => '2026-11-12',
  'end' => '2026-11-14',
  'status' => 'confirmed',
]]);

$day = null;
foreach ($calendar['days'] as $candidate) {
  if ($candidate['date'] === '2026-11-12') {
    $day = $candidate;
    break;
  }
}
Assert::true($day !== null);
Assert::same('guest@example.test', $day['items'][0]['email']);
Assert::same('+421123456789', $day['items'][0]['phone']);
Assert::same('Neskorý príchod', $day['items'][0]['note']);
Assert::same('2026-11-14', $day['items'][0]['end']);
