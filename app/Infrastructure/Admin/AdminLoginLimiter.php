<?php

declare(strict_types=1);

namespace App\Infrastructure\Admin;

use PDO;

/** Five failed logins per client IP per 15-minute window. No global account lockout. */
final class AdminLoginLimiter
{
    private const MAX_FAILURES = 5;

    public function __construct(private PDO $pdo)
    {
    }

    public function isBlocked(string $ipAddress): bool
    {
        // Fail closed if the web server cannot identify the client address.
        if (filter_var($ipAddress, FILTER_VALIDATE_IP) === false) {
            return true;
        }

        $statement = $this->pdo->prepare('SELECT blocked_until > now()
            FROM admin_login_attempts WHERE ip_address = CAST(:ip AS inet)');
        $statement->execute(['ip' => $ipAddress]);
        $blocked = $statement->fetchColumn();

        return $blocked === 't' || $blocked === '1';
    }

    public function recordFailure(string $ipAddress): void
    {
        if (filter_var($ipAddress, FILTER_VALIDATE_IP) === false) {
            return;
        }

        // INSERT .. ON CONFLICT is atomic for concurrent login attempts.
        $statement = $this->pdo->prepare(<<<'SQL'
            INSERT INTO admin_login_attempts (ip_address, failed_attempts, first_failed_at, last_failed_at)
            VALUES (CAST(:ip AS inet), 1, now(), now())
            ON CONFLICT (ip_address) DO UPDATE SET
                failed_attempts = CASE
                    WHEN admin_login_attempts.first_failed_at <= now() - INTERVAL '15 minutes' THEN 1
                    ELSE admin_login_attempts.failed_attempts + 1
                END,
                first_failed_at = CASE
                    WHEN admin_login_attempts.first_failed_at <= now() - INTERVAL '15 minutes' THEN now()
                    ELSE admin_login_attempts.first_failed_at
                END,
                blocked_until = CASE
                    WHEN admin_login_attempts.first_failed_at <= now() - INTERVAL '15 minutes' THEN NULL
                    WHEN admin_login_attempts.failed_attempts + 1 >= :max_failures THEN now() + INTERVAL '15 minutes'
                    ELSE NULL
                END,
                last_failed_at = now()
            SQL);
        $statement->execute(['ip' => $ipAddress, 'max_failures' => self::MAX_FAILURES]);
    }

    public function clear(string $ipAddress): void
    {
        if (filter_var($ipAddress, FILTER_VALIDATE_IP) === false) {
            return;
        }

        $statement = $this->pdo->prepare('DELETE FROM admin_login_attempts WHERE ip_address = CAST(:ip AS inet)');
        $statement->execute(['ip' => $ipAddress]);
    }

    /** Run daily from a scheduler to avoid retaining old client IP addresses. */
    public function prune(): int
    {
        return $this->pdo->exec("DELETE FROM admin_login_attempts WHERE last_failed_at < now() - INTERVAL '7 days'") ?: 0;
    }
}
