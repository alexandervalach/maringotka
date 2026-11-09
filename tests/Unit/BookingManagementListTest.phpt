<?php

declare(strict_types=1);

use App\Model\BookingManagementRepository;
use Tester\Assert;

require dirname(__DIR__) . '/bootstrap.php';

$repository = (new ReflectionClass(BookingManagementRepository::class))->newInstanceWithoutConstructor();
Assert::exception(static fn () => $repository->page('unknown', '', null, null), InvalidArgumentException::class);
Assert::exception(static fn () => $repository->page('', '', 3, 4), InvalidArgumentException::class);
Assert::exception(static fn () => $repository->page('', '', 0, null), InvalidArgumentException::class);
Assert::exception(static fn () => $repository->page('', '', null, -1), InvalidArgumentException::class);
Assert::exception(static fn () => $repository->page('', str_repeat('a', 101), null, null), InvalidArgumentException::class);

$source = file_get_contents(dirname(__DIR__, 2) . '/app/Model/BookingManagementRepository.php');
Assert::type('string', $source);
Assert::contains('ORDER BY id ', $source);
Assert::contains('LIMIT 26', $source);
Assert::contains('id < ?', $source);
Assert::contains('id > ?', $source);
Assert::notContains('OFFSET', $source);
