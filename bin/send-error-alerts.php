<?php

declare(strict_types=1);

use App\Infrastructure\Monitoring\ErrorStore;
use Nette\Mail\Message;
use Nette\Mail\SmtpMailer;

require dirname(__DIR__) . '/vendor/autoload.php';

$directory = dirname(__DIR__) . '/var/monitoring';
$store = new ErrorStore($directory);
$host = getenv('ALERT_SMTP_HOST') ?: '';
$recipient = getenv('ALERT_TO') ?: '';
$sender = getenv('ALERT_FROM') ?: '';
if ($host === '' || $recipient === '' || $sender === '') {
    fwrite(STDERR, "SMTP alerting is not configured; queued alerts remain on disk.\n");
    exit(2);
}

try {
    $lock = $store->acquireWorkerLock();
} catch (RuntimeException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(3);
}

$encryption = getenv('ALERT_SMTP_ENCRYPTION') ?: 'tls';
if (!in_array($encryption, ['tls', 'ssl'], true)) {
    fwrite(STDERR, "Only TLS or SSL SMTP encryption is supported.\n");
    exit(2);
}
$mailer = new SmtpMailer(
    host: $host,
    username: getenv('ALERT_SMTP_USER') ?: '',
    password: getenv('ALERT_SMTP_PASSWORD') ?: '',
    port: (int) (getenv('ALERT_SMTP_PORT') ?: ($encryption === 'ssl' ? 465 : 587)),
    encryption: $encryption,
    timeout: 10,
);

$errors = 0;
foreach ($store->pending() as $pendingPath) {
    $pending = $store->read($pendingPath);
    if ($pending === null || !isset($pending['fingerprint'])) {
        continue;
    }
    $fingerprint = (string) $pending['fingerprint'];
    if (!preg_match('/^[a-f0-9]{64}$/D', $fingerprint)) {
        continue;
    }
    $issue = $store->read($directory . '/issue-' . $fingerprint . '.json');
    if ($issue === null) {
        continue;
    }
    $message = new Message();
    $message->setFrom($sender)
        ->addTo($recipient)
        ->setSubject('[Maringotka][' . ($issue['last_release'] ?? 'unknown') . '] New error ' . substr($fingerprint, 0, 12))
        ->setBody(
            "A production error needs attention.\n\n" .
            'Type: ' . ($issue['type'] ?? 'unknown') . "\n" .
            'Location: ' . ($issue['location'] ?? 'unknown') . "\n" .
            "Fingerprint: {$fingerprint}\n" .
            'First seen: ' . ($issue['first_seen'] ?? '?') . "\n" .
            'Last seen: ' . ($issue['last_seen'] ?? '?') . "\n" .
            'Occurrences: ' . ($issue['count'] ?? '?') . "\n" .
            "Full stack trace: protected Tracy logs on server.\n",
        );
    try {
        $mailer->send($message);
        $store->markDelivered($pendingPath);
        echo 'Delivered: ' . substr($fingerprint, 0, 12) . "\n";
    } catch (Throwable $e) {
        $errors++;
        $store->incrementAttempt($pendingPath, $pending);
        error_log('Error alert SMTP dispatch failed: ' . $e::class);
    }
}
flock($lock, LOCK_UN);
fclose($lock);
exit($errors > 0 ? 1 : 0);
