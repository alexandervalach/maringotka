<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
  http_response_code(404);
  exit;
}

foreach (['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD'] as $name) {
  if (getenv($name) === false || getenv($name) === '') {
    fwrite(STDERR, "Missing environment variable: {$name}\n");
    exit(1);
  }
}

try {
  $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', getenv('DB_HOST'), getenv('DB_PORT'), getenv('DB_NAME'));
  $pdo = new PDO($dsn, getenv('DB_USER'), getenv('DB_PASSWORD'), [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_TIMEOUT => 5,
  ]);
  $pdo->query('SELECT 1')->fetchColumn();
  echo "PostgreSQL connectivity OK\n";
} catch (PDOException $e) {
  fwrite(STDERR, "PostgreSQL connectivity FAILED (details in container logs if available)\n");
  exit(1);
}
