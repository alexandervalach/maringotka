<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Model\BookingPriceCalculator;
use Tester\Assert;

$calculator = new BookingPriceCalculator();
$rates = [
  'first_night' => 12000,
  'additional_night' => 11000,
  'wellness' => 2000,
  'breakfast' => 2000,
  'romance' => 4000,
  'minimum_nights' => 1,
];

$basic = $calculator->calculate('2030-05-10', '2030-05-11', $rates);
Assert::same(1, $basic['nights']);
Assert::same(12000, $basic['accommodation_cents']);
Assert::same(0, $basic['extras_cents']);
Assert::same(12000, $basic['total_cents']);
Assert::count(1, $basic['items']);

$all = $calculator->calculate('2030-05-10', '2030-05-13', $rates, [
  'wellness' => true,
  'breakfast' => true,
  'romance' => true,
  'frizzante' => 2,
  'canvas_bag' => 1,
]);
Assert::same(3, $all['nights']);
Assert::same(34000, $all['accommodation_cents']);
Assert::same(18500, $all['extras_cents']);
Assert::same(52500, $all['total_cents']);
Assert::same(['first_night', 'additional_night', 'wellness', 'breakfast', 'romance', 'frizzante', 'canvas_bag'], array_column($all['items'], 'code'));
Assert::same(3, $all['items'][2]['quantity']);
Assert::same(2000, $all['items'][2]['unit_price_cents']);
Assert::same(2000, $all['items'][5]['total_cents']);

$changed = $rates;
$changed['first_night'] = 15000;
$changed['wellness'] = 2500;
Assert::same(31000, $calculator->calculate('2030-05-10', '2030-05-12', $changed, ['wellness' => true])['total_cents']);

foreach ([
  ['2030-02-30', '2030-03-03', $rates, []],
  ['2030-05-11', '2030-05-11', $rates, []],
  ['2030-05-12', '2030-05-11', $rates, []],
  ['2030-05-10', '2030-05-11', $rates, ['bikes' => true]],
  ['2030-05-10', '2030-05-11', $rates, ['frizzante' => -1]],
  ['2030-05-10', '2030-05-11', $rates, ['canvas_bag' => 21]],
  ['2030-05-10', '2030-05-11', $rates, ['wellness' => 1]],
] as [$arrival, $departure, $priceList, $choices]) {
  Assert::exception(static fn () => $calculator->calculate($arrival, $departure, $priceList, $choices), InvalidArgumentException::class);
}

$minTwo = $rates;
$minTwo['minimum_nights'] = 2;
Assert::exception(static fn () => $calculator->calculate('2030-05-10', '2030-05-11', $minTwo), InvalidArgumentException::class);
Assert::same(23000, $calculator->calculate('2030-05-10', '2030-05-12', $minTwo)['total_cents']);
