<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use PDO;
use RuntimeException;

final class ConnectionFactory
{
    public function create(): PDO
    {
        $host = $this->requiredEnv('DB_HOST');
        $port = $this->requiredEnv('DB_PORT');
        $name = $this->requiredEnv('DB_NAME');
        $user = $this->requiredEnv('DB_USER');
        $password = $this->requiredEnv('DB_PASSWORD');

        // Database credentials must never be placed in connection URLs.
        return new PDO(
            sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port, $name),
            $user,
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5],
        );
    }

    private function requiredEnv(string $key): string
    {
        $value = getenv($key);
        if ($value === false || $value === '') {
            throw new RuntimeException(sprintf('Missing database environment variable: %s', $key));
        }
        return $value;
    }
}
