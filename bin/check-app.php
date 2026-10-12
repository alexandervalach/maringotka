<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

try {
    $configurator = require dirname(__DIR__) . '/app/bootstrap.php';
    $container = $configurator->createContainer();
    $factory = $container->getByType(App\Infrastructure\Database\ConnectionFactory::class);
    $result = $factory->create()->query('SELECT 1')->fetchColumn();
    if ((int) $result !== 1) {
        throw new RuntimeException('Unexpected database response.');
    }
    echo "Nette container and PostgreSQL OK\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "Application smoke check FAILED (see protected Tracy log)\n");
    exit(1);
}
