<?php

declare(strict_types=1);

namespace App\Components\Mail;

use App\Helpers\PaymentEmailTemplate;
use InvalidArgumentException;

/**
 * Editable notification of a recorded payment with the invoice attached as PDF.
 */
final class BookingPaymentReceivedEmailTemplate
{
  private const SUBJECT = 'R-{{booking_id}} – Potvrdenie prijatia platby';

  /**
   * @return array{subject: string, body: string, show_logo: bool}
   */
  public static function defaults(): array
  {
    return ['subject' => self::SUBJECT, 'body' => self::defaultBody(), 'show_logo' => true];
  }

  /**
   * Loads the default editable email body from its Markdown asset.
   *
   * @return string
   */
  private static function defaultBody(): string
  {
    $body = @file_get_contents(__DIR__ . '/paid.md');

    if ($body === false) {
      throw new \RuntimeException('Chýba predvolená Markdown šablóna potvrdenia platby.');
    }

    return rtrim($body, "\r\n");
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
    foreach ([$subject, $body] as $text) {
      preg_match_all('/\{\{([^{}]*)\}\}/u', $text, $matches);
      foreach ($matches[1] as $placeholder) {
        if (!in_array($placeholder, ['booking_id', 'guest_name', 'arrival', 'departure', 'amount', 'invoice_number'], true)) {
          throw new InvalidArgumentException('Neznáma značka {{' . $placeholder . '}}.');
        }
      }
    }
  }

  /**
   * @param array<string, mixed> $booking Stored guest and stay details.
   * @param array{subject: string, body: string, show_logo: bool}|null $template
   * @return array{subject: string, body: string, html: string, show_logo: bool}
   */
  public static function content(array $booking, string $invoiceNumber, int $amountCents, ?array $template = null): array
  {
    $template ??= self::defaults();
    self::validate($template['subject'], $template['body']);
    $values = [
      '{{booking_id}}' => str_pad((string) (int) $booking['booking_id'], 6, '0', STR_PAD_LEFT),
      '{{guest_name}}' => (string) $booking['guest_name'],
      '{{arrival}}' => (string) $booking['arrival_date'],
      '{{departure}}' => (string) $booking['departure_date'],
      '{{amount}}' => number_format($amountCents / 100, 2, ',', ' ') . ' €',
      '{{invoice_number}}' => $invoiceNumber,
    ];
    $subject = strtr($template['subject'], $values);
    $body = strtr($template['body'], $values);
    return [
      'subject' => $subject,
      'body' => PaymentEmailTemplate::plainText($body),
      'html' => PaymentEmailTemplate::renderMarkdown($body),
      'show_logo' => $template['show_logo'],
    ];
  }
}
