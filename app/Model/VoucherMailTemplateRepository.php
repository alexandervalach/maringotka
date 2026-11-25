<?php

declare(strict_types=1);

namespace App\Model;

use App\Components\Mail\VoucherOrderEmailTemplate;
use App\Infrastructure\Database\ConnectionFactory;
use Nette\Database\Explorer;

/** Editable voucher messages stored separately from booking email templates in the UI. */
final class VoucherMailTemplateRepository
{
  private readonly Explorer $database;

  public function __construct(ConnectionFactory $connections)
  {
    $this->database = $connections->createExplorer();
  }

  /** @return array{subject: string, body: string, show_logo: bool} */
  public function get(string $event, string $locale): array
  {
    $key = self::key($event, $locale);
    $row = $this->database->query(
      'SELECT subject, body, show_logo FROM booking_mail_templates WHERE template_key = ?', $key,
    )->fetch();

    return $row === null ? (new VoucherOrderEmailTemplate())->defaults($event, $locale) : [
      'subject' => (string) $row['subject'],
      'body' => (string) $row['body'],
      'show_logo' => (bool) $row['show_logo'],
    ];
  }

  public function save(string $event, string $locale, string $subject, string $body, bool $showLogo): void
  {
    VoucherOrderEmailTemplate::validate($event, $subject, $body);
    $this->database->query(
      'INSERT INTO booking_mail_templates (template_key, subject, body, show_logo, updated_at)'
      . ' VALUES (?, ?, ?, ?, now()) ON CONFLICT (template_key) DO UPDATE SET'
      . ' subject = EXCLUDED.subject, body = EXCLUDED.body,'
      . ' show_logo = EXCLUDED.show_logo, updated_at = now()',
      self::key($event, $locale), $subject, $body, $showLogo,
    );
  }

  public function reset(string $event, string $locale): void
  {
    $this->database->query('DELETE FROM booking_mail_templates WHERE template_key = ?', self::key($event, $locale));
  }

  public static function key(string $event, string $locale): string
  {
    if (!in_array($event, ['ordered', 'paid'], true) || !in_array($locale, ['sk', 'en'], true)) {
      throw new \InvalidArgumentException('Neplatný typ šablóny.');
    }

    return 'voucher_' . $event . '_' . $locale;
  }
}
