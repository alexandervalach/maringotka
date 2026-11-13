<?php

declare(strict_types=1);

use Nette\Mail\Message;
use Nette\Mail\SmtpMailer;
use App\Model\SmtpTransportFactory;
use App\Helpers\BookingRequestEmailTemplate;
use App\Helpers\BookingConfirmationEmailTemplate;
use App\Model\BookingPaymentDetails;
use App\Helpers\PaymentEmailTemplate;

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
 * Formats a price in integer eurocents for booking notification emails.
 *
 * @param int $cents Exact price in eurocents.
 * @return string Human-readable euro amount.
 */
function bookingMailEuros(int $cents): string
{
  return number_format($cents / 100, 2, ',', ' ') . ' €';
}

/**
 * Renders only the immutable booking quote persisted at request time.
 * Older bookings intentionally have no quote, and are not re-priced.
 *
 * @param mixed $storedQuote JSONB value returned by PDO (usually a JSON string).
 * @param mixed $storedTotal Total amount in cents returned by PDO.
 * @return list<string> Quote lines; empty for legacy bookings without a snapshot.
 */
function bookingMailQuoteLines(mixed $storedQuote, mixed $storedTotal): array
{
  if ($storedQuote === null && $storedTotal === null) {
    return [];
  }
  if (!is_string($storedQuote) || (!is_int($storedTotal) && !is_string($storedTotal))) {
    throw new RuntimeException('Invalid booking price snapshot.');
  }
  $quote = json_decode($storedQuote, true, 512, JSON_THROW_ON_ERROR);
  if (!is_array($quote) || !isset($quote['items']) || !is_array($quote['items'])
    || !isset($quote['total_cents']) || !is_int($quote['total_cents'])
    || !is_numeric($storedTotal) || (string) (int) $storedTotal !== (string) $storedTotal
    || (int) $storedTotal !== $quote['total_cents']) {
    throw new RuntimeException('Inconsistent booking price snapshot.');
  }

  $labels = [
    'first_night' => 'Prvá noc',
    'additional_night' => 'Ďalšie noci',
    'wellness' => 'Wellness (sauna a kúpacia kaďa)',
    'breakfast' => 'Raňajkový kôš pre 2 osoby',
    'romance' => 'Romantické prekvapenie',
    'frizzante' => 'Tokajské Frizzante',
    'canvas_bag' => 'Plátená taška',
  ];
  $lines = ['Cenový rozpis (orientačná cena pri odoslaní žiadosti):'];
  $sum = 0;
  foreach ($quote['items'] as $item) {
    if (!is_array($item)
      || !isset($item['code'], $item['quantity'], $item['unit_price_cents'], $item['total_cents'])
      || !is_string($item['code']) || !isset($labels[$item['code']])
      || !is_int($item['quantity']) || $item['quantity'] < 1
      || !is_int($item['unit_price_cents']) || $item['unit_price_cents'] < 0
      || !is_int($item['total_cents']) || $item['total_cents'] < 0
      || $item['quantity'] * $item['unit_price_cents'] !== $item['total_cents']) {
      throw new RuntimeException('Invalid booking quote line item.');
    }
    $sum += $item['total_cents'];
    $lines[] = sprintf(
      '- %s: %d × %s = %s',
      $labels[$item['code']],
      $item['quantity'],
      bookingMailEuros($item['unit_price_cents']),
      bookingMailEuros($item['total_cents']),
    );
  }
  if ($sum !== $quote['total_cents']) {
    throw new RuntimeException('Booking quote total does not match its items.');
  }
  $lines[] = 'Spolu: ' . bookingMailEuros((int) $storedTotal);
  $lines[] = 'Žiadosť ešte neznamená potvrdenie rezervácie ani vykonanie platby.';
  return $lines;
}

/**
 * Formats one e-mail without disclosing cancellation credentials.
 *
 * @param array<string, mixed> $row Outbox event and reservation data.
 * @param string $contactAddress Public contact e-mail for cancellation requests.
 * @param string $contactPhone Optional public contact phone number.
 * @param array{subject: string, body: string, show_logo: bool}|null $receivedTemplate
 * @param array{subject: string, body: string, show_logo: bool}|null $confirmedTemplate
 * @return array{subject: string, body: string, html?: string, show_logo?: bool}
 */
function bookingMailContent(
  array $row,
  string $contactAddress = '',
  string $contactPhone = '',
  ?array $receivedTemplate = null,
  ?array $confirmedTemplate = null,
  ?string $paymentInstructions = null,
): array
{
  $number = 'R-' . str_pad((string) $row['booking_id'], 6, '0', STR_PAD_LEFT);
  $guest = (string) $row['guest_name'];
  $arrival = (string) $row['arrival_date'];
  $departure = (string) $row['departure_date'];
  $event = (string) $row['event_type'];
  $admin = $row['recipient_type'] === 'admin';

  if (!$admin && $event === 'received') {
    $rendered = BookingRequestEmailTemplate::content(
      $row,
      bookingMailQuoteLines($row['price_quote'] ?? null, $row['price_total_cents'] ?? null),
      $contactAddress,
      $receivedTemplate,
    );
    return $rendered;
  }

  if (!$admin && $event === 'confirmed') {
    $quoteLines = bookingMailQuoteLines($row['price_quote'] ?? null, $row['price_total_cents'] ?? null);
    // The common quote builder adds a warning intended only for pending requests.
    if ($quoteLines !== [] && str_starts_with($quoteLines[count($quoteLines) - 1], 'Žiadosť ešte neznamená')) {
      array_pop($quoteLines);
    }
    return BookingConfirmationEmailTemplate::content(
      $row,
      $quoteLines,
      $contactAddress,
      $paymentInstructions ?? 'Platobné údaje vám zašleme samostatne. Zatiaľ platbu neposielajte.',
      $confirmedTemplate,
    );
  }

  $headings = [
    'received' => 'Žiadosť bola prijatá',
    'confirmed' => 'Rezervácia bola potvrdená',
    'rejected' => 'Rezervácia bola zamietnutá',
    'cancelled' => 'Rezervácia bola zrušená',
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
  if (in_array($event, ['rejected', 'cancelled'], true) && trim((string) ($row['decision_reason'] ?? '')) !== '') {
    $lines[] = 'Dôvod ' . ($event === 'cancelled' ? 'zrušenia' : 'zamietnutia') . ': ' . trim((string) $row['decision_reason']);
  }
  if (!$admin) {
    if ($event === 'received' || $event === 'confirmed') {
      $lines[] = 'Ak potrebujete rezerváciu zrušiť, kontaktujte prevádzkovateľa. Zrušenie vybaví manuálne.';
    }
    if ($contactAddress !== '') {
      $lines[] = 'Kontakt prevádzkovateľa: ' . $contactAddress;
    }
    if ($contactPhone !== '') {
      $lines[] = 'Telefón prevádzkovateľa: ' . $contactPhone;
    }
  }
  foreach (bookingMailQuoteLines($row['price_quote'] ?? null, $row['price_total_cents'] ?? null) as $quoteLine) {
    $lines[] = $quoteLine;
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
       b.arrival_date, b.departure_date, b.created_at, b.decision_reason,
       b.price_quote, b.price_total_cents
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
      $contactPhone = getenv('BOOKING_CONTACT_PHONE');
      $receivedGuest = $event['event_type'] === 'received' && $event['recipient_type'] === 'guest';
      $receivedTemplate = null;
      if ($receivedGuest) {
        $templateRow = $pdo->query(
          "SELECT subject, body, show_logo FROM booking_mail_templates WHERE template_key = 'received'"
        )->fetch(PDO::FETCH_ASSOC);
        if (is_array($templateRow)) {
          $receivedTemplate = [
            'subject' => (string) $templateRow['subject'],
            'body' => (string) $templateRow['body'],
            'show_logo' => (bool) $templateRow['show_logo'],
          ];
        }
      }
      $confirmedGuest = $event['event_type'] === 'confirmed' && $event['recipient_type'] === 'guest';
      $confirmedTemplate = null;
      $paymentInstructions = null;
      if ($confirmedGuest) {
        $templateRow = $pdo->query(
          "SELECT subject, body, show_logo FROM booking_mail_templates WHERE template_key = 'confirmed'"
        )->fetch(PDO::FETCH_ASSOC);
        if (is_array($templateRow)) {
          $confirmedTemplate = [
            'subject' => (string) $templateRow['subject'],
            'body' => (string) $templateRow['body'],
            'show_logo' => (bool) $templateRow['show_logo'],
          ];
        }
        $account = $pdo->query(
          'SELECT iban, account_holder, bic FROM booking_payment_settings WHERE id = 1'
        )->fetch(PDO::FETCH_ASSOC);
        $settings = is_array($account) ? [
          'iban' => $account['iban'] === null ? null : (string) $account['iban'],
          'account_holder' => $account['account_holder'] === null ? null : (string) $account['account_holder'],
          'bic' => $account['bic'] === null ? null : (string) $account['bic'],
        ] : null;
        $createdAt = new DateTimeImmutable((string) $event['created_at']);
        $year = (int) $createdAt->setTimezone(new DateTimeZone('Europe/Bratislava'))->format('Y');
        $variableSymbol = BookingPaymentDetails::variableSymbol((int) $event['booking_id'], $year);
        $paymentInstructions = BookingConfirmationEmailTemplate::paymentInstructions(
          $settings,
          $event['price_total_cents'] === null ? null : (int) $event['price_total_cents'],
          $variableSymbol,
        );
      }
      $content = bookingMailContent(
        $event,
        $adminAddress,
        is_string($contactPhone) ? trim($contactPhone) : '',
        $receivedTemplate,
        $confirmedTemplate,
        $paymentInstructions,
      );
      $recipient = $event['recipient_type'] === 'admin' ? $adminAddress : (string) $event['guest_email'];
      $message = new Message();
      $message->setFrom($from)
        ->addTo($recipient)
        ->setSubject($content['subject'])
        ->setBody($content['body']);
      if (($receivedGuest || $confirmedGuest) && isset($content['html'], $content['show_logo'])) {
        $logoPath = dirname(__DIR__) . '/www/assets/images/logo.png';
        $message->setHtmlBody(
          PaymentEmailTemplate::htmlDocument($content['html'], $content['show_logo'], is_file($logoPath)),
          dirname($logoPath),
        );
      }
      $mailer->send($message);
      $stmt = $pdo->prepare('UPDATE booking_mail_outbox SET sent_at = now(), attempts = attempts + 1 WHERE id = ?');
      $stmt->execute([(int) $event['outbox_id']]);

      $mailKind = match ((string) $event['event_type']) {
        'received' => 'Potvrdenie prijatia žiadosti',
        'confirmed' => 'Potvrdenie rezervácie',
        'rejected' => 'Oznámenie o zamietnutí',
        'cancelled' => 'Oznámenie o zrušení',
        default => 'Oznámenie o rezervácii',
      };
      $recipientKind = $event['recipient_type'] === 'admin' ? 'administrátorovi' : 'hosťovi';
      $audit = $pdo->prepare(
        'INSERT INTO booking_audit_events (booking_id, event_type, detail, event_key)'
        . " VALUES (?, 'mail_sent', ?, ?) ON CONFLICT (event_key) DO NOTHING"
      );
      $audit->execute([
        (int) $event['booking_id'],
        $mailKind . ' odoslané ' . $recipientKind,
        'outbox:' . (string) $event['outbox_id'],
      ]);

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
    $from = bookingMailEnv('MAIL_FROM');
    $adminAddress = bookingMailEnv('BOOKING_ADMIN_EMAIL');
    if (!filter_var($from, FILTER_VALIDATE_EMAIL) || !filter_var($adminAddress, FILTER_VALIDATE_EMAIL)) {
      throw new RuntimeException('Invalid sender or administrator email address.');
    }
    $mailer = SmtpTransportFactory::create();
    $dsn = sprintf(
      'pgsql:host=%s;port=%s;dbname=%s',
      bookingMailEnv('DB_HOST'),
      bookingMailEnv('DB_PORT'),
      bookingMailEnv('DB_NAME'),
    );
    $pdo = new PDO($dsn, bookingMailEnv('DB_USER'), bookingMailEnv('DB_PASSWORD'), [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $result = bookingMailDrain($pdo, $mailer, $from, $adminAddress, 25);
    echo sprintf("Sent: %d; failed: %d\n", $result['sent'], $result['failed']);
    exit($result['failed'] === 0 ? 0 : 1);
  } catch (Throwable $error) {
    fwrite(STDERR, 'Booking mail worker error: ' . get_class($error) . "\n");
    exit(1);
  }
}
