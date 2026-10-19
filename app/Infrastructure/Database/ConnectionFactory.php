<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use Nette\Caching\Storages\MemoryStorage;
use Nette\Database\Connection;
use Nette\Database\Conventions\DiscoveredConventions;
use Nette\Database\Explorer;
use Nette\Database\Structure;
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

    return new PDO(
        sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port, $name),
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5],
    );
  }

  /**
   * Create an Explorer for ActiveRow-based feature repositories.
   * This supplements, rather than replaces, the existing PDO connection.
   *
   * @return Explorer Database Explorer configured for PostgreSQL.
   */
  public function createExplorer(): Explorer
  {
    $connection = new Connection(
      sprintf('pgsql:host=%s;port=%s;dbname=%s',
        $this->requiredEnv('DB_HOST'),
        $this->requiredEnv('DB_PORT'),
        $this->requiredEnv('DB_NAME'),
      ),
      $this->requiredEnv('DB_USER'),
      $this->requiredEnv('DB_PASSWORD'),
    );

    $storage = new MemoryStorage();
    $structure = new Structure($connection, $storage);

    return new Explorer($connection, $structure, new DiscoveredConventions($structure), $storage);
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
