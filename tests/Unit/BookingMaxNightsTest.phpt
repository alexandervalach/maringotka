<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Model\BookingPriceCalculator;
use App\Model\BookingRepository;
use Tester\Assert;

Assert::same(30, BookingPriceCalculator::MAX_NIGHTS);

$start = new DateTimeImmutable('today');
$arrival = $start->format('Y-m-d');
$lastAllowed = $start->modify('+30 days')->format('Y-m-d');
$tooLong = $start->modify('+31 days')->format('Y-m-d');

// These limits apply to server-side requests, not just the displayed quote.
BookingRepository::validateStay($arrival, $lastAllowed, 1);
Assert::exception(
  static fn () => BookingRepository::validateStay($arrival, $tooLong, 1),
  InvalidArgumentException::class,
);
