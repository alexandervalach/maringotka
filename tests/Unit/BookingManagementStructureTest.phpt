<?php

declare(strict_types=1);

use Tester\Assert;

require dirname(__DIR__) . '/bootstrap.php';

$base = dirname(__DIR__, 2);
$repository = file_get_contents($base . '/app/Model/BookingManagementRepository.php');
$presenter = file_get_contents($base . '/app/Presenters/BookingManagementPresenter.php');
$form = file_get_contents($base . '/app/Forms/BookingDecisionFormFactory.php');
Assert::type('string', $repository);
Assert::type('string', $presenter);
Assert::type('string', $form);
Assert::contains('FOR UPDATE', $repository);
Assert::contains("'pending' => ['confirmed', 'rejected', 'cancelled']", $repository);
Assert::contains("'confirmed' => ['cancelled']", $repository);
Assert::contains('requireAdmin()', $presenter);
Assert::contains('addError($error->getMessage())', $presenter);
Assert::contains("'cancelled' => 'Zrušiť rezerváciu'", $form);
