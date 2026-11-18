<?php

declare(strict_types=1);

namespace App\Components\Mail;

use RuntimeException;

/**
 * Reads externally maintained plain-text Markdown voucher email templates.
 */
final class VoucherOrderEmailTemplate
{
  /**
   * @param array<string, string> $values
   * @return array{subject: string, body: string}
   */
  public function render(string $event, string $locale, array $values): array
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

    $replace = [];

    foreach ($values as $name => $value) {
      $replace['{{' . $name . '}}'] = str_replace(["\r", "\n"], ' ', $value);
    }

    $subject = match ([$event, $locale]) {
      ['ordered', 'en'] => 'Gift voucher order – ' . ($values['reference'] ?? ''),
      ['ordered', 'sk'] => 'Objednávka darčekového poukazu – ' . ($values['reference'] ?? ''),
      ['paid', 'en'] => 'Your gift voucher – ' . ($values['reference'] ?? ''),
      default => 'Váš darčekový poukaz – ' . ($values['reference'] ?? ''),
    };

    return ['subject' => $subject, 'body' => strtr($body, $replace)];
  }
}
