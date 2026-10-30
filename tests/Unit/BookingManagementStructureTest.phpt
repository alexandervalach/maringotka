<?php

declare(strict_types=1);

use Tester\Assert;

require dirname(__DIR__) . '/bootstrap.php';

$base = dirname(__DIR__, 2);
$repository = file_get_contents($base . '/app/Model/BookingManagementRepository.php');
$presenter = file_get_contents($base . '/app/Presenters/BookingManagementPresenter.php');
Assert::type('string', $repository);
Assert::type('string', $presenter);
Assert::contains('FOR UPDATE', $repository);
Assert::contains("\$row->status !== 'pending'", $repository);
Assert::contains("'confirmed', 'rejected'", $repository);
Assert::contains('requireAdmin()', $presenter);
Assert::contains('addError($error->getMessage())', $presenter);
