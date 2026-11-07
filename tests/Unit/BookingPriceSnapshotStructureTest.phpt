<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use Tester\Assert;

$root = dirname(__DIR__, 2);
$repository = file_get_contents($root . '/app/Model/BookingRepository.php');
$presenter = file_get_contents($root . '/app/Presenters/BookingPresenter.php');
$migration = file_get_contents($root . '/migrations/202611070001_booking_price_snapshot.sql');

Assert::type('string', $repository);
Assert::type('string', $presenter);
Assert::type('string', $migration);
Assert::contains('price_quote', $repository);
Assert::contains('price_total_cents', $repository);
Assert::contains('json_encode($quote, JSON_THROW_ON_ERROR)', $repository);
Assert::contains('$minimum, $quote)', $presenter);
Assert::contains('price_quote jsonb', $migration);
Assert::contains('price_total_cents integer', $migration);
