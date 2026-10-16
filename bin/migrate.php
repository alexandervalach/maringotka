<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Infrastructure\Database\ConnectionFactory;
use App\Infrastructure\Database\MigrationRunner;

try {
    $pdo = (new ConnectionFactory())->create();
    $runner = new MigrationRunner($pdo, dirname(__DIR__) . '/migrations');
    $names = $runner->migrate();
    foreach ($names as $name) {
        echo 'Applied: ', $name, PHP_EOL;
    }
    echo $names === [] ? "No pending migrations.\n" : "Migration complete.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Migration failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
