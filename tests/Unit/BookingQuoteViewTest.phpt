<?php

declare(strict_types=1);

use App\Model\BookingQuoteView;
use Tester\Assert;

require dirname(__DIR__) . '/bootstrap.php';

$formatter = new BookingQuoteView();
$quote = [
  'nights' => 3,
  'total_cents' => 42000,
  'items' => [
    ['code' => 'first_night', 'quantity' => 1, 'unit_price_cents' => 12000, 'total_cents' => 12000, 'unit' => 'night'],
    ['code' => 'additional_night', 'quantity' => 2, 'unit_price_cents' => 11000, 'total_cents' => 22000, 'unit' => 'night'],
    ['code' => 'wellness', 'quantity' => 3, 'unit_price_cents' => 2000, 'total_cents' => 6000, 'unit' => 'night'],
    ['code' => 'frizzante', 'quantity' => 2, 'unit_price_cents' => 1000, 'total_cents' => 2000, 'unit' => 'item'],
  ],
];
$result = $formatter->parse(json_encode($quote, JSON_THROW_ON_ERROR), 42000);
Assert::same(42000, $result['total_cents']);
Assert::same(3, $result['nights']);
Assert::same('Wellness', $result['lines'][2]['label']);
Assert::true($result['lines'][2]['extra']);
Assert::false($result['lines'][0]['extra']);
Assert::same('420,00 €', BookingQuoteView::euros(42000));
Assert::null($formatter->parse(null, null));
Assert::exception(static function () use ($formatter, $quote): void {
  $formatter->parse(json_encode($quote, JSON_THROW_ON_ERROR), 41999);
}, InvalidArgumentException::class);
$quote['items'][3]['total_cents'] = 3000;
Assert::exception(static function () use ($formatter, $quote): void {
  $formatter->parse($quote, 42000);
}, InvalidArgumentException::class);
