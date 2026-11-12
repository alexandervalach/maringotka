<?php

declare(strict_types=1);

namespace App\Helpers;

use InvalidArgumentException;

/**
 * Editable acknowledgement for a newly submitted reservation request.
 * This message must never imply that a stay has been approved or paid.
 */
final class BookingRequestEmailTemplate
{
  private const SUBJECT = 'R-{{booking_id}} – Žiadosť bola prijatá';

  private const BODY = <<<'MARKDOWN'
Dobrý deň, {{guest_name}},

prijali sme vašu **žiadosť o rezerváciu**. Žiadosť čaká na schválenie; pobyt zatiaľ **nie je potvrdený** a **nevyžadujeme platbu**. Po preverení dostupnosti vás budeme kontaktovať.

**Číslo žiadosti:** R-{{booking_id}}

**Príchod:** {{arrival}}

**Odchod:** {{departure}}

**Zhrnutie vybraných položiek:**

{{quote}}

Ak potrebujete žiadosť zmeniť alebo zrušiť, kontaktujte prevádzkovateľa: {{contact}}.

Ďakujeme.
MARKDOWN;

  /**
   * @return array{subject: string, body: string, show_logo: bool}
   */
  public static function defaults(): array
  {
    return ['subject' => self::SUBJECT, 'body' => self::BODY, 'show_logo' => true];
  }

  /**
   * @return void
   */
  public static function validate(string $subject, string $body): void
  {
    if (trim($subject) === '' || strlen($subject) > 180 || strpbrk($subject, "\r\n") !== false) {
      throw new InvalidArgumentException('Predmet musí byť jeden riadok s najviac 180 znakmi.');
    }
    if (trim($body) === '' || strlen($body) > 10000) {
      throw new InvalidArgumentException('Text musí mať najviac 10 000 znakov.');
    }
    foreach (['{{booking_id}}', '{{arrival}}', '{{departure}}', '{{quote}}'] as $required) {
      if (!str_contains($body, $required)) {
        throw new InvalidArgumentException('Chýba povinná značka ' . $required . '.');
      }
    }
    foreach ([$subject, $body] as $value) {
      preg_match_all('/\{\{([^{}]*)\}\}/u', $value, $matches);
      foreach ($matches[1] as $name) {
        if (!in_array($name, ['booking_id', 'guest_name', 'arrival', 'departure', 'quote', 'contact'], true)) {
          throw new InvalidArgumentException('Neznáma značka {{' . $name . '}}.');
        }
      }
    }
  }

  /**
   * @param array<string, mixed> $booking Outbox booking row.
   * @param list<string> $quoteLines Verified immutable booking quote lines.
   * @param array{subject: string, body: string, show_logo: bool}|null $template
   * @return array{subject: string, body: string, html: string, show_logo: bool}
   */
  public static function content(array $booking, array $quoteLines, string $contact, ?array $template = null): array
  {
    $template ??= self::defaults();
    self::validate($template['subject'], $template['body']);
    $values = [
      '{{booking_id}}' => str_pad((string) (int) $booking['booking_id'], 6, '0', STR_PAD_LEFT),
      '{{guest_name}}' => (string) $booking['guest_name'],
      '{{arrival}}' => (string) $booking['arrival_date'],
      '{{departure}}' => (string) $booking['departure_date'],
      '{{quote}}' => $quoteLines === [] ? 'Cenový rozpis nie je dostupný.' : implode("\n", $quoteLines),
      '{{contact}}' => $contact === '' ? 'prevádzkovateľa' : $contact,
    ];
    $body = strtr($template['body'], $values);
    $subject = preg_replace('/[\r\n]+/u', ' ', strtr($template['subject'], $values));
    return [
      'subject' => $subject ?? '',
      'body' => PaymentEmailTemplate::plainText($body),
      'html' => PaymentEmailTemplate::renderMarkdown($body),
      'show_logo' => $template['show_logo'],
    ];
  }
}
