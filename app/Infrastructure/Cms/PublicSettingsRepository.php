<?php

declare(strict_types=1);

namespace App\Infrastructure\Cms;

use InvalidArgumentException;
use PDO;

final class PublicSettingsRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function get(string $key): ?string
    {
        self::assertKey($key);
        $stmt = $this->pdo->prepare('SELECT value FROM public_settings WHERE key = :key');
        $stmt->execute(['key' => $key]);
        $result = $stmt->fetchColumn();
        return $result === false ? null : (string) $result;
    }

    public function set(string $key, string $value): void
    {
        self::assertKey($key);
        // Public, non-secret values only. Do not put credentials, tokens or payment secrets here.
        $stmt = $this->pdo->prepare(<<<'SQL'
            INSERT INTO public_settings (key, value) VALUES (:key, :value)
            ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value, updated_at = now()
            SQL);
        $stmt->execute(['key' => $key, 'value' => $value]);
    }

    private static function assertKey(string $key): void
    {
        if (!preg_match('/^[a-z][a-z0-9_]*$/D', $key) || strlen($key) > 80) {
            throw new InvalidArgumentException('Invalid public setting key.');
        }
    }
}
