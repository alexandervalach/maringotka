<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use PDO;
use RuntimeException;

/** Applies immutable, numbered SQL files in a transaction under a PostgreSQL advisory lock. */
final class MigrationRunner
{
    public function __construct(private PDO $pdo, private string $directory)
    {
    }

    /** @return list<string> Names of migrations applied in this run. */
    public function migrate(): array
    {
        $files = glob(rtrim($this->directory, '/') . '/*.sql');
        if ($files === false) {
            throw new RuntimeException('Unable to list SQL migrations');
        }
        sort($files, SORT_STRING);
        if ($files === []) {
            throw new RuntimeException('No SQL migrations found');
        }

        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec('SELECT pg_advisory_xact_lock(66006, 1)');
            $this->pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (name varchar(255) PRIMARY KEY, sha256 char(64) NOT NULL, applied_at timestamptz NOT NULL DEFAULT now())');
            $applied = $this->pdo->query('SELECT name, sha256 FROM schema_migrations')->fetchAll(PDO::FETCH_KEY_PAIR);
            $known = [];
            foreach ($files as $file) {
                $name = basename($file);
                $known[$name] = true;
                $hash = hash_file('sha256', $file);
                $sql = file_get_contents($file);
                if ($hash === false || $sql === false) {
                    throw new RuntimeException('Cannot read migration: ' . $name);
                }
                if (isset($applied[$name]) && !hash_equals($applied[$name], $hash)) {
                    throw new RuntimeException('Applied migration has changed: ' . $name);
                }
            }
            foreach ($applied as $name => $_) {
                if (!isset($known[$name])) {
                    throw new RuntimeException('Previously applied migration file is missing: ' . $name);
                }
            }

            $new = [];
            $insert = $this->pdo->prepare('INSERT INTO schema_migrations(name, sha256) VALUES (:name, :sha256)');
            foreach ($files as $file) {
                $name = basename($file);
                if (isset($applied[$name])) {
                    continue;
                }
                $sql = file_get_contents($file);
                if ($sql === false) {
                    throw new RuntimeException('Cannot read migration: ' . $name);
                }
                $this->pdo->exec($sql);
                $insert->execute(['name' => $name, 'sha256' => hash('sha256', $sql)]);
                $new[] = $name;
            }
            $this->pdo->commit();
            return $new;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
