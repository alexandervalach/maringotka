<?php

declare(strict_types=1);

use Tester\Assert;

require dirname(__DIR__) . '/bootstrap.php';

$base = dirname(__DIR__, 2);
$repository = file_get_contents($base . '/app/Model/BookingManagementRepository.php');
$presenter = file_get_contents($base . '/app/Presenters/BookingManagementPresenter.php');
$template = file_get_contents($base . '/app/Presenters/templates/BookingManagement/default.latte');
Assert::type('string', $repository);
Assert::type('string', $presenter);
Assert::type('string', $template);
Assert::contains('ORDER BY id ', $repository);
Assert::contains('LIMIT 26', $repository);
Assert::contains('id < ?', $repository);
Assert::contains('id > ?', $repository);
Assert::notContains('OFFSET', $repository);
Assert::contains('requireAdmin()', $presenter);
Assert::contains('name="status"', $template);
Assert::contains('name="q"', $template);
Assert::contains('booking-admin-table', $template);
