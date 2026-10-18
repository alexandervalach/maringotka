<?php

declare(strict_types=1);

use App\Infrastructure\Admin\AdminCredentials;
use App\Infrastructure\Database\ConnectionFactory;

require dirname(__DIR__) . '/vendor/autoload.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

// A generated secret avoids exposing the password in argv, shell history or env vars.
$password = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
$credentials = new AdminCredentials((new ConnectionFactory())->create());
$credentials->setPasswordHash(AdminCredentials::hashPassword($password));
fwrite(STDOUT, "Administrator password (save in password manager; shown once):\n$password\n");
