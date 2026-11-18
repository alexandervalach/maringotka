<?php

declare(strict_types=1);

use App\Components\Mail\VoucherOrderEmailTemplate;
use App\Components\Voucher\VoucherPdf;
use App\Infrastructure\Database\ConnectionFactory;
use App\Model\GiftVoucherOrderRepository;
use App\Model\GiftVoucherRepository;
use Nette\Mail\Message;
use Nette\Mail\SmtpMailer;

require __DIR__ . '/../vendor/autoload.php';

/**
 * Read one required SMTP setting without leaking its contents.
 */
function voucherEnv(string $key): string
{
  $value = getenv($key);

  if (!is_string($value) || $value === '') {
    throw new RuntimeException('Missing configuration: ' . $key);
  }

  return $value;
}

/**
 * Process queued voucher emails; no email is sent in an HTTP request.
 *
 * @return array{sent: int, failed: int}
 */
function voucherDrain(PDO $pdo, SmtpMailer $mailer, string $from, int $limit = 25): array
{
  $sent = 0;
  $failed = 0;
  $templates = new VoucherOrderEmailTemplate();
  $vouchers = new GiftVoucherRepository(new ConnectionFactory());
  $pdf = new VoucherPdf();

  for ($i = 0; $i < $limit; $i++) {
    $event = null;
    $pdo->beginTransaction();

    try {
      $stmt = $pdo->query(<<<'SQL'
SELECT m.id, m.order_id, m.event_type,
       o.reference, o.customer_name, o.customer_email, o.locale,
       o.status, o.price_cents, o.document, o.voucher_id
FROM gift_voucher_mail_outbox m
JOIN gift_voucher_orders o ON o.id = m.order_id
WHERE m.sent_at IS NULL AND m.next_attempt_at <= now() AND m.attempts < 10
ORDER BY m.next_attempt_at, m.id
LIMIT 1 FOR UPDATE OF m SKIP LOCKED
SQL);
      $event = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($event === false) {
        $pdo->commit();
        break;
      }

      // A payment request becomes obsolete when payment was verified before dispatch.
      if ($event['event_type'] === 'ordered' && $event['status'] !== 'pending') {
        $skip = $pdo->prepare(
          'UPDATE gift_voucher_mail_outbox SET sent_at = now() WHERE id = ? AND sent_at IS NULL',
        );
        $skip->execute([(int) $event['id']]);
        $pdo->commit();
        continue;
      }

      // Read immutable order values; do not recalculate current campaign prices.
      $snapshot = json_decode((string) $event['document'], true, 512, JSON_THROW_ON_ERROR);

      if (!is_array($snapshot)) {
        throw new RuntimeException('Invalid voucher order snapshot.');
      }

      $vars = [
        'customer_name' => (string) $event['customer_name'],
        'campaign_name' => (string) (($event['locale'] === 'en' && !empty($snapshot['campaign_name_en']))
          ? $snapshot['campaign_name_en'] : ($snapshot['campaign_name'] ?? '')),
        'reference' => (string) $event['reference'],
        'amount' => number_format((int) $event['price_cents'] / 100, 2, ',', ' ') . ' EUR',
      ];

      if ($event['event_type'] === 'ordered') {
        $bank = $pdo->query('SELECT iban, account_holder FROM booking_payment_settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC);

        if (!is_array($bank) || empty($bank['iban']) || empty($bank['account_holder'])) {
          throw new RuntimeException('Voucher bank account is not configured.');
        }

        $vars['iban'] = (string) $bank['iban'];
        $vars['recipient'] = (string) $bank['account_holder'];
        $vars['variable_symbol'] = GiftVoucherOrderRepository::variableSymbol((int) $event['order_id']);
      } elseif ($event['event_type'] === 'paid') {
        if ($event['status'] !== 'paid' || $event['voucher_id'] === null) {
          throw new RuntimeException('Voucher order is not paid or has no issued voucher.');
        }
      } else {
        throw new RuntimeException('Unknown voucher mail event.');
      }

      $content = $templates->render((string) $event['event_type'], (string) $event['locale'], $vars);
      $message = new Message();
      $message->setFrom($from)
        ->addTo((string) $event['customer_email'])
        ->setSubject($content['subject'])
        ->setBody($content['body']);

      if ($event['event_type'] === 'paid') {
        $record = $vouchers->find((int) $event['voucher_id']);

        if ($record === null || !is_array($record['document'])) {
          throw new RuntimeException('Issued voucher not found.');
        }

        $message->addAttachment('darcekovy-poukaz.pdf', $pdf->render($record['document']), 'application/pdf');
      }

      $mailer->send($message);
      $update = $pdo->prepare('UPDATE gift_voucher_mail_outbox SET sent_at = now(), attempts = attempts + 1 WHERE id = ?');
      $update->execute([(int) $event['id']]);
      $pdo->commit();
      $sent++;
    } catch (Throwable $error) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }

      if ($event === null || $event === false) {
        throw $error;
      }

      error_log('Voucher mail failed (' . get_class($error) . ').');
      $retry = $pdo->prepare(<<<'SQL'
UPDATE gift_voucher_mail_outbox
SET attempts = attempts + 1,
    next_attempt_at = now() + (LEAST(3600, 60 * (attempts + 1)) * interval '1 second')
WHERE id = ? AND sent_at IS NULL
SQL);
      $retry->execute([(int) $event['id']]);
      $failed++;
    }
  }

  return ['sent' => $sent, 'failed' => $failed];
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
  try {
    $encryption = voucherEnv('SMTP_ENCRYPTION');

    if (!in_array($encryption, ['ssl', 'tls'], true)) {
      throw new RuntimeException('Invalid SMTP encryption.');
    }

    $port = filter_var(voucherEnv('SMTP_PORT'), FILTER_VALIDATE_INT);

    if (!is_int($port) || $port < 1 || $port > 65535) {
      throw new RuntimeException('Invalid SMTP port.');
    }

    $from = voucherEnv('MAIL_FROM');

    if (filter_var($from, FILTER_VALIDATE_EMAIL) === false) {
      throw new RuntimeException('Invalid sender address.');
    }

    $pdo = (new ConnectionFactory())->create();
    $mailer = new SmtpMailer(
      host: voucherEnv('SMTP_HOST'),
      username: voucherEnv('SMTP_USER'),
      password: voucherEnv('SMTP_PASSWORD'),
      port: $port,
      encryption: $encryption,
    );
    $result = voucherDrain($pdo, $mailer, $from);
    echo sprintf("Sent: %d; failed: %d\n", $result['sent'], $result['failed']);
    exit($result['failed'] === 0 ? 0 : 1);
  } catch (Throwable $error) {
    fwrite(STDERR, 'Voucher mail worker error: ' . get_class($error) . "\n");
    exit(1);
  }
}
