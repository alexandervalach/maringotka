<?php

declare(strict_types=1);

use App\Model\BookingManagementRepository;
use Tester\Assert;

require dirname(__DIR__) . '/bootstrap.php';

// Verify input validation through the public method; no database is needed.
$repository = (new ReflectionClass(BookingManagementRepository::class))->newInstanceWithoutConstructor();
Assert::exception(static fn () => $repository->statusHistory(0), InvalidArgumentException::class);
Assert::exception(static fn () => $repository->statusHistory(-7), InvalidArgumentException::class);

// Preserve the history ordering contract until a database integration test
// replaces it. Avoid checking presenter implementation and exact PHP syntax.
$source = file_get_contents(dirname(__DIR__, 2) . '/app/Model/BookingManagementRepository.php');
Assert::type('string', $source);
Assert::contains('FROM booking_status_history WHERE booking_id = ? ORDER BY id DESC', $source);

$template = file_get_contents(dirname(__DIR__, 2) . '/app/Presenters/templates/BookingManagement/edit.latte');
Assert::type('string', $template);
Assert::contains('booking-history-table', $template);
