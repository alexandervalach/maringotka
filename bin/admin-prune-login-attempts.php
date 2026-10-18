<?php

declare(strict_types=1);

use App\Infrastructure\Admin\AdminLoginLimiter;
use App\Infrastructure\Database\ConnectionFactory;

require dirname(__DIR__) . '/vendor/autoload.php';

if (PHP_SAPI !== 'cli') {
    exit(1);
}

try {
    $removed = (new AdminLoginLimiter((new ConnectionFactory())->create()))->prune();
    fwrite(STDOUT, sprintf("Removed %d expired IP rate-limit records.\n", $removed));
} catch (Throwable $e) {
    fwrite(STDERR, 'Unable to prune login attempts: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
