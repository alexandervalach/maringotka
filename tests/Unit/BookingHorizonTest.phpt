<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Model\BookingRepository;
use Tester\Assert;

$today = new DateTimeImmutable('today');
$calendarUntil = (new DateTimeImmutable('first day of this month'))->modify('+12 months');
$lastArrival = $calendarUntil->modify('-1 day');

// A checkout on the last boundary is allowed: the end is exclusive.
BookingRepository::validateStay($lastArrival->format('Y-m-d'), $calendarUntil->format('Y-m-d'), 1);

// Arrival outside the displayed calendar window is forbidden.
Assert::exception(
  static fn () => BookingRepository::validateStay(
    $calendarUntil->format('Y-m-d'),
    $calendarUntil->modify('+1 day')->format('Y-m-d'),
    1,
  ),
  InvalidArgumentException::class,
);

// A stay cannot cross the displayed calendar boundary.
Assert::exception(
  static fn () => BookingRepository::validateStay(
    $lastArrival->format('Y-m-d'),
    $calendarUntil->modify('+1 day')->format('Y-m-d'),
    1,
  ),
  InvalidArgumentException::class,
);

// Existing minimum-night and past-date rules remain in effect.
Assert::exception(
  static fn () => BookingRepository::validateStay(
    $lastArrival->format('Y-m-d'),
    $calendarUntil->format('Y-m-d'),
    2,
  ),
  InvalidArgumentException::class,
);
Assert::exception(
  static fn () => BookingRepository::validateStay(
    $today->modify('-1 day')->format('Y-m-d'),
    $today->format('Y-m-d'),
    1,
  ),
  InvalidArgumentException::class,
);
