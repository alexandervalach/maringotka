<?php

declare(strict_types=1);

namespace App\Helpers;

use InvalidArgumentException;

/**
 * Editable guest email sent after an administrator confirms a booking.
 * All monetary values originate from the saved quote, never a recalculation.
 */
final class BookingConfirmationEmailTemplate
{
  private const SUBJECT = 'R-{{booking_id}} – Rezervácia bola potvrdená';

  private const BODY = <<<'MARKDOWN'
Dobrý deň, {{guest_name}},

vaša **rezervácia bola potvrdená**.

**Číslo rezervácie:** R-{{booking_id}}

**Príchod:** {{arrival}}

**Odchod:** {{departure}}

**Zhrnutie rezervácie:**

{{quote}}

{{payment_instructions}}

Ak potrebujete dohodnúť zmenu rezervácie, kontaktujte prevádzkovateľa: {{contact}}. Zmeny vybavuje administrátor.

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

    foreach (['{{booking_id}}', '{{arrival}}', '{{departure}}', '{{quote}}', '{{payment_instructions}}'] as $required) {
      if (!str_contains($body, $required)) {
        throw new InvalidArgumentException('Chýba povinná značka ' . $required . '.');
      }
    }

    foreach ([$subject, $body] as $value) {
      preg_match_all('/\{\{([^{}]*)\}\}/u', $value, $matches);
      foreach ($matches[1] as $name) {
        if (!in_array($name, ['booking_id', 'guest_name', 'arrival', 'departure', 'quote', 'payment_instructions', 'contact'], true)) {
          throw new InvalidArgumentException('Neznáma značka {{' . $name . '}}.');
        }
      }
    }
  }

  /**
   * Formats only bank details explicitly configured by the administrator.
   * When missing, the email does not instruct the guest to pay yet.
   *
   * @param array{iban: string|null, account_holder: string|null, bic: string|null}|null $settings
   * @return string
   */
  public static function paymentInstructions(?array $settings, ?int $amountCents, ?string $variableSymbol): string
  {
    if ($settings === null || trim((string) ($settings['iban'] ?? '')) === ''
      || trim((string) ($settings['account_holder'] ?? '')) === ''
      || $amountCents === null || $amountCents <= 0 || $variableSymbol === null) {
      return 'Platobné údaje vám zašleme samostatne. Zatiaľ platbu neposielajte.';
    }

    $lines = [
      'Rezervácia je potvrdená. Platbu môžete vykonať bankovým prevodom:',
      'Príjemca: ' . trim($settings['account_holder']),
      'IBAN: ' . trim($settings['iban']),
    ];

    if (trim((string) ($settings['bic'] ?? '')) !== '') {
      $lines[] = 'BIC: ' . trim((string) $settings['bic']);
    }

    $lines[] = 'Suma: ' . intdiv($amountCents, 100) . '.' . str_pad((string) ($amountCents % 100), 2, '0', STR_PAD_LEFT) . ' EUR';
    $lines[] = 'Variabilný symbol: ' . $variableSymbol;

    return implode("\n", $lines);
  }

  /**
   * @param array<string, mixed> $booking Persisted booking row.
   * @param list<string> $quoteLines Saved price summary, never recomputed.
   * @param array{subject: string, body: string, show_logo: bool}|null $template
   * @return array{subject: string, body: string, html: string, show_logo: bool}
   */
  public static function content(
    array $booking,
    array $quoteLines,
    string $contact,
    string $paymentInstructions,
    ?array $template = null,
  ): array {
    $template ??= self::defaults();
    self::validate($template['subject'], $template['body']);
    $values = [
      '{{booking_id}}' => str_pad((string) (int) $booking['booking_id'], 6, '0', STR_PAD_LEFT),
      '{{guest_name}}' => (string) $booking['guest_name'],
      '{{arrival}}' => (string) $booking['arrival_date'],
      '{{departure}}' => (string) $booking['departure_date'],
      '{{quote}}' => $quoteLines === [] ? 'Cenový rozpis nie je dostupný.' : implode("\n", $quoteLines),
      '{{payment_instructions}}' => $paymentInstructions,
      '{{contact}}' => trim($contact) === '' ? 'prevádzkovateľa' : trim($contact),
    ];
    
    $body = strtr($template['body'], $values);
    $subject = strtr($template['subject'], $values);

    return [
      'subject' => $subject,
      'body' => PaymentEmailTemplate::plainText($body),
      'html' => PaymentEmailTemplate::renderMarkdown($body),
      'show_logo' => $template['show_logo'],
    ];
  }
}
