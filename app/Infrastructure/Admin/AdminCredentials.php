<?php

declare(strict_types=1);

namespace App\Infrastructure\Admin;

use PDO;
use RuntimeException;

/** Single-account credentials; IP rate limiting is performed separately. */
final class AdminCredentials
{
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

    /** CLI-only administrator reset; does not change the username. */
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
     * Always verify the password hash for an existing account, even if the
     * username is incorrect. Never disclose which credential was invalid.
     */
    public function verify(string $username, string $password): bool
    {
        $statement = $this->pdo->query('SELECT username, password_hash FROM admin_account WHERE singleton_id = 1');
        if ($statement === false) {
            throw new RuntimeException('Unable to query administrator credentials.');
        }

        /** @var array{username:string,password_hash:string}|false $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return false;
        }

        $passwordValid = password_verify($password, $row['password_hash']);
        $usernameValid = hash_equals($row['username'], $username);
        if (!$passwordValid || !$usernameValid) {
            return false;
        }

        if (password_needs_rehash($row['password_hash'], PASSWORD_ARGON2ID)) {
            $update = $this->pdo->prepare('UPDATE admin_account SET password_hash = :hash,
                password_changed_at = now(), updated_at = now() WHERE singleton_id = 1');
            $update->execute(['hash' => self::hashPassword($password)]);
        }

        return true;
    }
}
