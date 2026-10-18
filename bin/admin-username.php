<?php

declare(strict_types=1);

use App\Infrastructure\Database\ConnectionFactory;

require dirname(__DIR__) . '/vendor/autoload.php';

if (PHP_SAPI !== 'cli') {
    exit(1);
}

if ($argc !== 2 || preg_match('/\A[a-z][a-z0-9_.-]{2,63}\z/D', $argv[1]) !== 1) {
    fwrite(STDERR, "Usage: php bin/admin-username.php lowercase-username\n(3-64 characters; a-z, 0-9, underscore, dash or dot; starts with a letter)\n");
    exit(1);
}

try {
    $statement = (new ConnectionFactory())->create()->prepare(
        'UPDATE admin_account SET username = :username, updated_at = now() WHERE singleton_id = 1',
    );
    $statement->execute(['username' => $argv[1]]);
    if ($statement->rowCount() !== 1) {
        throw new RuntimeException('Administrator account not initialized. Run bin/admin-init.php first.');
    }
    fwrite(STDOUT, "Administrator username updated.\n");
} catch (Throwable $e) {
    fwrite(STDERR, 'Unable to update administrator username: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
