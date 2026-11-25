<?php

declare(strict_types=1);

namespace App\Components\Mail;

use RuntimeException;
use InvalidArgumentException;

/**
 * Reads externally maintained plain-text Markdown voucher email templates.
 */
final class VoucherOrderEmailTemplate
{
  /**
   * @param array<string, string> $values
   * @param array{subject: string, body: string}|null $template
   * @return array{subject: string, body: string}
   */
  public function render(string $event, string $locale, array $values, ?array $template = null): array
  {
    if (!in_array($event, ['ordered', 'paid'], true)) {
      throw new RuntimeException('Unknown voucher email event.');
    }

    $locale = $locale === 'en' ? 'en' : 'sk';
    $path = __DIR__ . '/voucher-' . $event . '.' . $locale . '.md';
    $body = file_get_contents($path);

    if ($body === false) {
      throw new RuntimeException('Missing voucher email template.');
    }

    $subject = match ([$event, $locale]) {
      ['ordered', 'en'] => 'Gift voucher order – {{reference}}',
      ['ordered', 'sk'] => 'Objednávka darčekového poukazu – {{reference}}',
      ['paid', 'en'] => 'Your gift voucher – {{reference}}',
      default => 'Váš darčekový poukaz – {{reference}}',
    };

    if ($template !== null) {
      self::validate($event, (string) $template['subject'], (string) $template['body']);
      $subject = (string) $template['subject'];
      $body = (string) $template['body'];
    }

    $replace = [];

    foreach ($values as $name => $value) {
      $replace['{{' . $name . '}}'] = str_replace(["\r", "\n"], ' ', $value);
    }

    return ['subject' => strtr($subject, $replace), 'body' => strtr($body, $replace)];
  }
  /**
   * @return array{subject: string, body: string, show_logo: bool}
   */
  public function defaults(string $event, string $locale): array
  {
    $sample = $this->render($event, $locale, ['reference' => '{{reference}}']);

    return ['subject' => $sample['subject'],
      'body' => (string) file_get_contents(__DIR__ . '/voucher-' . $event . '.' . ($locale === 'en' ? 'en' : 'sk') . '.md'),
      'show_logo' => true];
  }

  public static function validate(string $event, string $subject, string $body): void
  {
    if (!in_array($event, ['ordered', 'paid'], true)
      || trim($subject) === '' || strlen($subject) > 180 || strpbrk($subject, "\r\n") !== false
      || trim($body) === '' || strlen($body) > 10000) {
      throw new InvalidArgumentException('Neplatný predmet alebo text e-mailu.');
    }

    $allowed = ['customer_name', 'campaign_name', 'reference', 'amount',
      'recipient', 'iban', 'variable_symbol'];

    foreach ([$subject, $body] as $text) {
      preg_match_all('/\{\{([^{}]*)\}\}/u', $text, $matches);
      foreach ($matches[1] as $name) {
        if (!in_array($name, $allowed, true)) {
          throw new InvalidArgumentException('Neznáma značka {{' . $name . '}}.');
        }
      }
    }

    if ($event === 'ordered') {
      foreach (['{{amount}}', '{{iban}}', '{{variable_symbol}}'] as $required) {
        if (!str_contains($body, $required)) {
          throw new InvalidArgumentException('Chýba povinná značka ' . $required . '.');
        }
      }
    }
  }

}
