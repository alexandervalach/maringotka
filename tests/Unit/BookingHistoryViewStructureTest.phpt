<?php

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$root = dirname(__DIR__, 2);
$repository = file_get_contents($root . '/app/Model/BookingManagementRepository.php');
$presenter = file_get_contents($root . '/app/Presenters/BookingManagementPresenter.php');
$template = file_get_contents($root . '/app/Presenters/templates/BookingManagement/edit.latte');

Assert::type('string', $repository);
Assert::type('string', $presenter);
Assert::type('string', $template);
Assert::contains('function statusHistory(int $bookingId): array', $repository);
Assert::contains('FROM booking_status_history WHERE booking_id = ? ORDER BY id DESC', $repository);
Assert::contains('iterator_to_array($row)', $repository);
Assert::contains("$this->bookings->statusHistory($id)", $presenter);
Assert::contains('booking-decision-options', $template);
Assert::contains('booking-history-table', $template);
Assert::contains('booking-history.css', $template);
