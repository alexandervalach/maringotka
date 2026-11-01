<?php

declare(strict_types=1);

/**
 * Read-only diagnostic for the existing booking_mail_outbox table.
 * This script never sends, retries or modifies messages.
 */

/**
 * Converts database aggregates into a stable, non-sensitive status report.
 *
 * @param array<string, mixed> $row Database aggregate row.
 * @return array{due: int, scheduled: int, exhausted: int, sent: int, oldestDueSeconds: int, healthy: bool}
 */
function bookingMailStatusSummary(array $row): array
{
  $due = (int) ($row['due_count'] ?? 0);
  $scheduled = (int) ($row['scheduled_count'] ?? 0);
  $exhausted = (int) ($row['exhausted_count'] ?? 0);
  $sent = (int) ($row['sent_count'] ?? 0);
  $oldestDueSeconds = max(0, (int) ($row['oldest_due_seconds'] ?? 0));

  return [
    'due' => $due,
    'scheduled' => $scheduled,
    'exhausted' => $exhausted,
    'sent' => $sent,
    'oldestDueSeconds' => $oldestDueSeconds,
    'healthy' => $exhausted === 0 && $oldestDueSeconds < 3600,
  ];
}

/**
 * Reads a database setting without echoing its value.
 *
 * @param string $name Environment variable name.
 * @return string Non-empty value.
 */
function bookingMailStatusEnv(string $name): string
{
  $value = getenv($name);
  if (!is_string($value) || $value === '') {
    throw new RuntimeException('Missing environment variable: ' . $name);
  }
  return $value;
}

/**
 * Reads queue counts in one consistent, read-only SQL statement.
 *
 * @param PDO $pdo PostgreSQL connection.
 * @return array{due: int, scheduled: int, exhausted: int, sent: int, oldestDueSeconds: int, healthy: bool}
 */
function bookingMailStatusRead(PDO $pdo): array
{
  $sql = <<<'SQL'
SELECT
  COUNT(*) FILTER (WHERE sent_at IS NULL AND attempts < 10 AND next_attempt_at <= now()) AS due_count,
  COUNT(*) FILTER (WHERE sent_at IS NULL AND attempts < 10 AND next_attempt_at > now()) AS scheduled_count,
  COUNT(*) FILTER (WHERE sent_at IS NULL AND attempts >= 10) AS exhausted_count,
  COUNT(*) FILTER (WHERE sent_at IS NOT NULL) AS sent_count,
  COALESCE(
    FLOOR(EXTRACT(EPOCH FROM (now() - MIN(created_at) FILTER (
      WHERE sent_at IS NULL AND attempts < 10 AND next_attempt_at <= now()
    ))))::bigint,
    0
  ) AS oldest_due_seconds
FROM booking_mail_outbox
SQL;
  $statement = $pdo->query($sql);
  if ($statement === false) {
    throw new RuntimeException('Could not query booking mail outbox.');
  }
  $row = $statement->fetch(PDO::FETCH_ASSOC);
  if (!is_array($row)) {
    throw new RuntimeException('Booking mail outbox returned no summary.');
  }
  return bookingMailStatusSummary($row);
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
  try {
    $dsn = sprintf(
      'pgsql:host=%s;port=%s;dbname=%s',
      bookingMailStatusEnv('DB_HOST'),
      bookingMailStatusEnv('DB_PORT'),
      bookingMailStatusEnv('DB_NAME'),
    );
    $pdo = new PDO(
      $dsn,
      bookingMailStatusEnv('DB_USER'),
      bookingMailStatusEnv('DB_PASSWORD'),
      [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
    );
    $status = bookingMailStatusRead($pdo);
    echo sprintf("Ready for delivery: %d\n", $status['due']);
    echo sprintf("Waiting for retry: %d\n", $status['scheduled']);
    echo sprintf("Failed permanently (10 attempts): %d\n", $status['exhausted']);
    echo sprintf("Sent: %d\n", $status['sent']);
    echo sprintf("Oldest ready message age: %d seconds\n", $status['oldestDueSeconds']);
    echo $status['healthy'] ? "Status: OK\n" : "Status: ATTENTION REQUIRED\n";
    exit($status['healthy'] ? 0 : 2);
  } catch (Throwable $error) {
    // Avoid logging SQL exception messages because they can include connection details.
    fwrite(STDERR, 'Booking mail status unavailable (' . get_class($error) . ").\n");
    exit(1);
  }
}
