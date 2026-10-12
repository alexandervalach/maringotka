<?php

declare(strict_types=1);

namespace App\Infrastructure\Monitoring;

use RuntimeException;
use Throwable;

/** File-backed deduplication; no database dependency during an outage. */
final class ErrorStore
{
    public function __construct(private readonly string $directory)
    {
    }

    public static function fingerprint(Throwable $error): string
    {
        $frames = array_slice($error->getTrace(), 0, 5);
        $sites = [];
        foreach ($frames as $frame) {
            $sites[] = ($frame['class'] ?? '') . $frame['function'] . ($frame['file'] ?? '');
        }
        return hash('sha256', $error::class . "\n" . $error->getFile() . "\n" . implode("\n", $sites));
    }

    /** @return array{fingerprint:string, count:int, queued:bool} */
    public function record(Throwable $error, string $release = 'unknown'): array
    {
        $this->ensureDirectory();
        $key = self::fingerprint($error);
        $path = $this->directory . '/issue-' . $key . '.json';
        $lock = fopen($this->directory . '/.lock-' . $key, 'c');
        if ($lock === false) {
            throw new RuntimeException('Cannot open monitoring lock');
        }
        try {
            if (!flock($lock, LOCK_EX)) {
                throw new RuntimeException('Cannot lock monitoring issue');
            }
            $existing = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
            $now = gmdate('c');
            $count = (int) ($existing['count'] ?? 0) + 1;
            $new = !is_array($existing);
            $data = [
                'fingerprint' => $key,
                'type' => $error::class,
                'location' => basename($error->getFile()) . ':' . $error->getLine(),
                'first_seen' => $existing['first_seen'] ?? $now,
                'last_seen' => $now,
                'first_release' => $existing['first_release'] ?? $release,
                'last_release' => $release,
                'count' => $count,
                'status' => $existing['status'] ?? 'open',
            ];
            $reopened = !$new && ($existing['status'] ?? '') === 'resolved';
            if ($reopened) {
                $data['status'] = 'open';
            }
            $this->saveAtomic($path, $data);
            if ($new || $reopened) {
                $this->saveAtomic($this->directory . '/pending-' . $key . '.json', [
                    'fingerprint' => $key, 'created_at' => $now, 'attempts' => 0,
                ]);
            }
            return ['fingerprint' => $key, 'count' => $count, 'queued' => $new || $reopened];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @return list<string> */
    public function pending(): array
    {
        $this->ensureDirectory();
        return glob($this->directory . '/pending-*.json') ?: [];
    }

    /** @return array<string,mixed>|null */
    public function read(string $path): ?array
    {
        $decoded = json_decode((string) @file_get_contents($path), true);
        return is_array($decoded) ? $decoded : null;
    }

    public function markDelivered(string $pendingPath): void
    {
        if (!unlink($pendingPath) && is_file($pendingPath)) {
            throw new RuntimeException('Cannot remove delivered notification');
        }
    }

    /** @return resource */
    public function acquireWorkerLock()
    {
        $this->ensureDirectory();
        $handle = fopen($this->directory . '/.worker.lock', 'c');
        if ($handle === false || !flock($handle, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException('Monitoring worker already running or unable to lock');
        }
        return $handle;
    }

    /** @param array<string, mixed> $pending */
    public function incrementAttempt(string $pendingPath, array $pending): void
    {
        $pending['attempts'] = (int) ($pending['attempts'] ?? 0) + 1;
        $pending['last_attempt'] = gmdate('c');
        $this->saveAtomic($pendingPath, $pending);
    }

    private function ensureDirectory(): void
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
            throw new RuntimeException('Unable to create monitoring directory');
        }
    }

    /** @param array<string,mixed> $data */
    private function saveAtomic(string $path, array $data): void
    {
        $temporary = tempnam($this->directory, 'tmp-');
        if ($temporary === false) {
            throw new RuntimeException('Cannot create monitoring temporary file');
        }
        try {
            $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
            if (file_put_contents($temporary, $json, LOCK_EX) === false || !rename($temporary, $path)) {
                throw new RuntimeException('Cannot write monitoring state');
            }
            chmod($path, 0600);
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }
}
