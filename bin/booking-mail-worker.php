<?php

declare(strict_types=1);

use Nette\Mail\Message;
use Nette\Mail\SmtpMailer;

require __DIR__ . '/../vendor/autoload.php';

/**
 * Loads a required environment variable without exposing its value.
 *
 * @param string $name Variable name.
 * @return string Configured value.
 */
function bookingMailEnv(string $name): string
{
  $value = getenv($name);
  if (!is_string($value) || $value === '') {
    throw new RuntimeException(sprintf('Missing environment variable: %s', $name));
  }
  return $value;
}

/**
 * Formats one e-mail without disclosing cancellation credentials.
 *
 * @param array<string, mixed> $row Outbox event and reservation data.
 * @return array{subject: string, body: string}
 */
function bookingMailContent(array $row): array
{
  $number = 'R-' . str_pad((string) $row['booking_id'], 6, '0', STR_PAD_LEFT);
  $guest = (string) $row['guest_name'];
  $arrival = (string) $row['arrival_date'];
  $departure = (string) $row['departure_date'];
  $event = (string) $row['event_type'];
  $admin = $row['recipient_type'] === 'admin';

  $headings = [
    'received' => 'Žiadosť bola prijatá',
    'confirmed' => 'Rezervácia bola potvrdená',
    'rejected' => 'Rezervácia bola zamietnutá',
  ];
  if (!isset($headings[$event])) {
    throw new RuntimeException('Unknown booking event.');
  }

  $subject = sprintf('%s – %s', $number, $headings[$event]);
  $lines = [
    $headings[$event] . '.',
    'Číslo rezervácie: ' . $number,
    'Hosť: ' . $guest,
    'Príchod: ' . $arrival,
    'Odchod: ' . $departure,
  ];
  if ($event === 'received') {
    $lines[] = 'Žiadosť čaká na schválenie; pobyt zatiaľ nie je potvrdený.';
  }
  if ($event === 'rejected' && trim((string) ($row['decision_reason'] ?? '')) !== '') {
    $lines[] = 'Dôvod zamietnutia: ' . trim((string) $row['decision_reason']);
  }
  if ($admin) {
    $lines[] = 'Kontakt: ' . (string) $row['guest_email'];
    if ($row['guest_phone'] !== null && (string) $row['guest_phone'] !== '') {
      $lines[] = 'Telefón: ' . (string) $row['guest_phone'];
    }
    if (trim((string) $row['guest_note']) !== '') {
      $lines[] = 'Poznámka: ' . (string) $row['guest_note'];
    }
  }
  return ['subject' => $subject, 'body' => implode("\n", $lines) . "\n"];
}

/**
 * Sends at most the requested number of due messages.
 *
 * @param PDO $pdo Database connection.
 * @param SmtpMailer $mailer SMTP transport.
 * @param string $from Sender address.
 * @param string $adminAddress Administrator address.
 * @param int $limit Maximum messages in one run.
 * @return array{sent: int, failed: int} Processing statistics.
 */
function bookingMailDrain(PDO $pdo, SmtpMailer $mailer, string $from, string $adminAddress, int $limit): array
{
  $sent = 0;
  $failed = 0;
  for ($i = 0; $i < $limit; $i++) {
    $pdo->beginTransaction();
    try {
      $stmt = $pdo->query(<<<'SQL'
SELECT o.id AS outbox_id, o.booking_id, o.event_type, o.recipient_type,
       b.guest_name, b.guest_email, b.guest_phone, b.guest_note,
       b.arrival_date, b.departure_date, b.decision_reason
FROM booking_mail_outbox o
JOIN booking_requests b ON b.id = o.booking_id
WHERE o.sent_at IS NULL AND o.next_attempt_at <= now() AND o.attempts < 10
ORDER BY o.next_attempt_at, o.id
LIMIT 1 FOR UPDATE OF o SKIP LOCKED
SQL);
      $event = $stmt->fetch(PDO::FETCH_ASSOC);
      if ($event === false) {
        $pdo->commit();
        break;
      }
      $content = bookingMailContent($event);
      $recipient = $event['recipient_type'] === 'admin' ? $adminAddress : (string) $event['guest_email'];
      $message = new Message();
      $message->setFrom($from)
        ->addTo($recipient)
        ->setSubject($content['subject'])
        ->setBody($content['body']);
      $mailer->send($message);
      $stmt = $pdo->prepare('UPDATE booking_mail_outbox SET sent_at = now(), attempts = attempts + 1 WHERE id = ?');
      $stmt->execute([(int) $event['outbox_id']]);
      $pdo->commit();
      $sent++;
    } catch (Throwable $error) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      if (!isset($event) || $event === false) {
        throw $error;
      }
      // Only a generic error is logged. SMTP messages may contain credentials/PII.
      error_log('Booking mail delivery failed (' . get_class($error) . ').');
      $retry = $pdo->prepare(<<<'SQL'
UPDATE booking_mail_outbox
SET attempts = attempts + 1,
    next_attempt_at = now() + (LEAST(3600, 60 * (attempts + 1)) * interval '1 second')
WHERE id = ? AND sent_at IS NULL
SQL);
      $retry->execute([(int) $event['outbox_id']]);
      $failed++;
    }
    unset($event);
  }
  return ['sent' => $sent, 'failed' => $failed];
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
  try {
    if (!class_exists(SmtpMailer::class)) {
      throw new RuntimeException('Missing dependency nette/mail. Run composer require nette/mail.');
    }
    $host = bookingMailEnv('SMTP_HOST');
    $username = bookingMailEnv('SMTP_USER');
    $password = bookingMailEnv('SMTP_PASSWORD');
    $from = bookingMailEnv('MAIL_FROM');
    $adminAddress = bookingMailEnv('BOOKING_ADMIN_EMAIL');
    if (!filter_var($from, FILTER_VALIDATE_EMAIL) || !filter_var($adminAddress, FILTER_VALIDATE_EMAIL)) {
      throw new RuntimeException('Invalid sender or administrator email address.');
    }
    $encryption = bookingMailEnv('SMTP_ENCRYPTION');
    if (!in_array($encryption, ['ssl', 'tls'], true)) {
      throw new RuntimeException('SMTP_ENCRYPTION must be ssl or tls.');
    }
    $port = filter_var(bookingMailEnv('SMTP_PORT'), FILTER_VALIDATE_INT);
    if (!is_int($port) || $port < 1 || $port > 65535) {
      throw new RuntimeException('Invalid SMTP_PORT.');
    }
    $dsn = sprintf(
      'pgsql:host=%s;port=%s;dbname=%s',
      bookingMailEnv('DB_HOST'),
      bookingMailEnv('DB_PORT'),
      bookingMailEnv('DB_NAME'),
    );
    $pdo = new PDO($dsn, bookingMailEnv('DB_USER'), bookingMailEnv('DB_PASSWORD'), [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $mailer = new SmtpMailer(
      host: $host,
      username: $username,
      password: $password,
      port: $port,
      encryption: $encryption,
    );
    $result = bookingMailDrain($pdo, $mailer, $from, $adminAddress, 25);
    echo sprintf("Sent: %d; failed: %d\n", $result['sent'], $result['failed']);
    exit($result['failed'] === 0 ? 0 : 1);
  } catch (Throwable $error) {
    fwrite(STDERR, 'Booking mail worker error: ' . get_class($error) . "\n");
    exit(1);
  }
}
