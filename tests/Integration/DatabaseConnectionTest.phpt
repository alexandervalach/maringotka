<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Infrastructure\Database\ConnectionFactory;
use Tester\Assert;

// Intentionally read-only. Never mutate the development database in smoke tests.
$pdo = (new ConnectionFactory())->create();
Assert::same('1', (string) $pdo->query('SELECT 1')->fetchColumn());
