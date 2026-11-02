<?php

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$root = dirname(__DIR__, 2);
$component = file_get_contents($root . '/app/Components/UpcomingArrivalsControl.php');
$template = file_get_contents($root . '/app/Components/templates/upcomingArrivals.latte');

Assert::type('string', $component);
Assert::type('string', $template);
Assert::contains("status = ? AND arrival_date >= ?", $component);
Assert::contains("'confirmed'", $component);
Assert::contains('ORDER BY arrival_date ASC, id ASC LIMIT 5', $component);
Assert::contains('iterator_to_array($row)', $component);
Assert::contains('admin-upcoming-widget', $template);
Assert::contains('count($upcomingArrivals) === 0', $template);
