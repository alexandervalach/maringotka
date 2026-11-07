<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/bin/booking-mail-worker.php';

use App\Model\BookingCalendarMonth;
use App\Model\BookingPriceCalculator;
use App\Model\BookingQuoteView;
use Tester\Assert;

// The same immutable quote must be interpretable across the public form,
// admin detail, calendar preview and mail worker.
$rates = [
  'first_night' => 12000,
  'additional_night' => 11000,
  'wellness' => 2000,
  'breakfast' => 2000,
  'romance' => 4000,
  'minimum_nights' => 1,
];
$calculator = new BookingPriceCalculator();
$view = new BookingQuoteView();

$quote = $calculator->calculate('2030-12-07', '2030-12-10', $rates, [
  'wellness' => true,
  'breakfast' => true,
  'romance' => true,
  'frizzante' => 2,
  'canvas_bag' => 1,
]);
Assert::same(3, $quote['nights']);
Assert::same(52500, $quote['total_cents']);
Assert::same(52500, array_sum(array_column($quote['items'], 'total_cents')));

$storedJson = json_encode($quote, JSON_THROW_ON_ERROR);
$admin = $view->parse($storedJson, (string) $quote['total_cents']);
Assert::true($admin !== null);
Assert::same(52500, $admin['total_cents']);
Assert::same(3, $admin['nights']);
$extras = array_values(array_filter($admin['lines'], static fn (array $item): bool => $item['extra']));
Assert::same(['wellness', 'breakfast', 'romance', 'frizzante', 'canvas_bag'], array_column($extras, 'code'));
Assert::same([3, 3, 1, 2, 1], array_column($extras, 'quantity'));

$mailLines = bookingMailQuoteLines($storedJson, (string) $quote['total_cents']);
Assert::true(in_array('Spolu: 525,00 €', $mailLines, true));
Assert::true(count($mailLines) > 5);

$row = [
  'booking_id' => 123,
  'guest_name' => 'Testovací hosť',
  'arrival_date' => '2030-12-07',
  'departure_date' => '2030-12-10',
  'event_type' => 'received',
  'recipient_type' => 'guest',
  'guest_email' => 'test@example.invalid',
  'guest_phone' => '',
  'guest_note' => '',
  'price_quote' => $storedJson,
  'price_total_cents' => (string) $quote['total_cents'],
];
$received = bookingMailContent($row, 'operator@example.invalid');
Assert::contains('Spolu: 525,00 €', $received['body']);
Assert::contains('Žiadosť čaká na schválenie', $received['body']);

$row['event_type'] = 'confirmed';
$confirmed = bookingMailContent($row, 'operator@example.invalid');
Assert::contains('Rezervácia bola potvrdená', $confirmed['subject']);
Assert::contains('Spolu: 525,00 €', $confirmed['body']);

// Existing bookings without snapshots remain supported; no current rates are applied.
Assert::null($view->parse(null, null));
Assert::same([], bookingMailQuoteLines(null, null));
$row['price_quote'] = null;
$row['price_total_cents'] = null;
Assert::false(str_contains(bookingMailContent($row)['body'], 'Spolu:'));

// A subsequent price-list change must not change the historical snapshot.
$changedRates = $rates;
$changedRates['first_night'] = 18000;
Assert::true($calculator->calculate('2030-12-07', '2030-12-10', $changedRates)['total_cents'] !== $quote['total_cents']);
Assert::same(52500, $view->parse($storedJson, 52500)['total_cents']);

$calendar = BookingCalendarMonth::build(new DateTimeImmutable('2030-12-01'), [[
  'id' => 123,
  'guest' => 'Testovací hosť',
  'start' => $quote['arrival'],
  'end' => $quote['departure'],
  'status' => 'confirmed',
  'quoteExtras' => array_map(
    static fn (array $line): array => [
      'code' => $line['code'],
      'label' => $line['label'],
      'quantity' => $line['quantity'],
    ],
    $extras,
  ),
  'quoteTotal' => BookingQuoteView::euros($quote['total_cents']),
  'quoteState' => 'available',
]]);
$stayDates = [];
foreach ($calendar['agenda'] as $day) {
  foreach ($day['items'] as $item) {
    if ($item['id'] === 123) {
      $stayDates[] = $day['date'];
      Assert::same(3, $item['nights']);
      Assert::same(5, count($item['quoteExtras']));
      Assert::same('525,00 €', $item['quoteTotal']);
      Assert::same($day['date'] === '2030-12-07', $item['arrival']);
    }
  }
}
Assert::same(['2030-12-07', '2030-12-08', '2030-12-09'], $stayDates);

// Exactly 30 nights is allowed; 31 nights never gets priced.
$lastAllowed = $calculator->calculate('2030-05-01', '2030-05-31', $rates);
Assert::same(30, $lastAllowed['nights']);
Assert::exception(
  static fn () => $calculator->calculate('2030-05-01', '2030-06-01', $rates),
  InvalidArgumentException::class,
);

// Cross-view integrity checks must reject tampered totals.
Assert::exception(
  static fn () => $view->parse($storedJson, 52499),
  InvalidArgumentException::class,
);
Assert::exception(
  static fn () => bookingMailQuoteLines($storedJson, 52499),
  RuntimeException::class,
);
