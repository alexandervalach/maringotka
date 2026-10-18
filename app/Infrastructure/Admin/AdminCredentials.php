<?php

declare(strict_types=1);

namespace App\Infrastructure\Admin;

use PDO;
use RuntimeException;

/** Credential checking only. Session authentication belongs to a later commit. */
final class AdminCredentials
{
    private const MAX_ATTEMPTS = 5;

    public function __construct(private PDO $pdo)
    {
    }

    public static function hashPassword(string $password): string
    {
        if (strlen($password) < 16) {
            throw new RuntimeException('Administrator password must contain at least 16 bytes.');
        }
        return password_hash($password, PASSWORD_ARGON2ID);
    }

    /**
     * @param string $hash 
     *
     * Explicit administrator-controlled reset; also unlocks the account. 
     * */
    public function setPasswordHash(string $hash): void
    {
        if (!str_starts_with($hash, '$argon2id$')) {
            throw new RuntimeException('Only Argon2id password hashes are supported.');
        }
        $statement = $this->pdo->prepare('INSERT INTO admin_account (singleton_id, password_hash) VALUES (1, :hash)
            ON CONFLICT (singleton_id) DO UPDATE SET password_hash = EXCLUDED.password_hash,
            failed_attempts = 0, locked_until = NULL, password_changed_at = now(), updated_at = now()');
        $statement->execute(['hash' => $hash]);
    }

    /**
     * @param string $password
     * 
     * Constant external outcome for incorrect credentials and locked account.
     * Application code must not disclose lockout state to the unauthenticated caller.
     */
    public function verify(string $password): bool
    {
        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->query('SELECT password_hash, failed_attempts,
                (locked_until IS NOT NULL AND locked_until > now()) AS is_locked
                FROM admin_account WHERE singleton_id = 1 FOR UPDATE');
            if ($statement === false) {
                throw new RuntimeException('Unable to query administrator credentials.');
            }
            /** @var array{password_hash:string,failed_attempts:int|string,is_locked:bool|string}|false $row */
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            if ($row === false) {
                $this->pdo->commit();
                return false;
            }
            $locked = $row['is_locked'] === true || $row['is_locked'] === 't' || $row['is_locked'] === '1';
            if ($locked) {
                $this->pdo->commit();
                return false;
            }
            if (password_verify($password, $row['password_hash'])) {
                $hash = $row['password_hash'];
                if (password_needs_rehash($hash, PASSWORD_ARGON2ID)) {
                    $hash = self::hashPassword($password);
                }
                $update = $this->pdo->prepare('UPDATE admin_account SET password_hash = :hash,
                    failed_attempts = 0, locked_until = NULL, updated_at = now() WHERE singleton_id = 1');
                $update->execute(['hash' => $hash]);
                $this->pdo->commit();
                return true;
            }
            $attempts = (int) $row['failed_attempts'] + 1;
            $update = $this->pdo->prepare('UPDATE admin_account SET failed_attempts = :attempts,
                locked_until = CASE WHEN :lock = 1 THEN now() + INTERVAL \'15 minutes\' ELSE NULL END,
                updated_at = now() WHERE singleton_id = 1');
            $update->execute(['attempts' => $attempts, 'lock' => $attempts >= self::MAX_ATTEMPTS ? 1 : 0]);
            $this->pdo->commit();
            return false;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
