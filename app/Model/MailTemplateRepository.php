<?php

declare(strict_types=1);

namespace App\Model;

use App\Helpers\BookingRequestEmailTemplate;
use App\Infrastructure\Database\ConnectionFactory;
use Nette\Database\Explorer;

/**
 * Shared storage for editable transactional email templates.
 * Additional template types can reuse this repository.
 */
final class MailTemplateRepository
{
  private readonly Explorer $database;

  public function __construct(ConnectionFactory $connections)
  {
    $this->database = $connections->createExplorer();
  }

  /**
   * @return array{subject: string, body: string, show_logo: bool}
   */
  public function received(): array
  {
    $row = $this->database->query(
      'SELECT subject, body, show_logo FROM booking_mail_templates WHERE template_key = ?',
      'received',
    )->fetch();
    return $row === null ? BookingRequestEmailTemplate::defaults() : [
      'subject' => (string) $row['subject'],
      'body' => (string) $row['body'],
      'show_logo' => (bool) $row['show_logo'],
    ];
  }

  public function saveReceived(string $subject, string $body, bool $showLogo): void
  {
    BookingRequestEmailTemplate::validate($subject, $body);
    $this->database->query(
      'INSERT INTO booking_mail_templates (template_key, subject, body, show_logo, updated_at)'
      . ' VALUES (?, ?, ?, ?, now()) ON CONFLICT (template_key) DO UPDATE SET'
      . ' subject = EXCLUDED.subject, body = EXCLUDED.body,'
      . ' show_logo = EXCLUDED.show_logo, updated_at = now()',
      'received', $subject, $body, $showLogo,
    );
  }

  public function resetReceived(): void
  {
    $this->database->query('DELETE FROM booking_mail_templates WHERE template_key = ?', 'received');
  }
}
