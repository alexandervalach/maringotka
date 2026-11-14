<?php

declare(strict_types=1);

namespace App\Components\Invoice;

use Dompdf\Dompdf;
use Dompdf\Options;
use DomainException;

/**
 * Renders an immutable invoice snapshot with external Markdown and CSS assets.
 */
final class InvoicePdf
{
  /**
   * @param array<string, mixed> $document
   * @return string
   */
  public function render(array $document): string
  {
    if (!class_exists(Dompdf::class)) {
      throw new DomainException('Chýba PDF knižnica dompdf/dompdf.');
    }

    $options = new Options();
    $options->set('isRemoteEnabled', false);
    $options->set('isPhpEnabled', false);
    $options->set('defaultFont', 'DejaVu Sans');

    $pdf = new Dompdf($options);
    $pdf->setPaper('A4', 'portrait');
    $pdf->loadHtml($this->html($document), 'UTF-8');
    $pdf->render();

    return $pdf->output();
  }

  /**
   * @param array<string, mixed> $document
   * @return string
   */
  public function html(array $document): string
  {
    $supplier = $document['supplier'] ?? null;
    $customer = $document['customer'] ?? null;
    $lines = $document['lines'] ?? null;

    if (!is_array($supplier) || !is_array($customer) || !is_array($lines)) {
      throw new DomainException('Neplatné údaje faktúry.');
    }

    $template = self::readAsset(__DIR__ . '/invoice.md');
    $css = self::readAsset(__DIR__ . '/invoice.css');
    $logo = self::readAsset(__DIR__ . '/assets/invoice-logo.jpg');
    $arrival = new \DateTimeImmutable((string) ($document['arrival'] ?? ''));
    $departure = new \DateTimeImmutable((string) ($document['departure'] ?? ''));
    $nights = (int) $arrival->diff($departure)->format('%r%a');

    if ($nights < 1) {
      throw new DomainException('Neplatný termín pobytu.');
    }

    $rows = '';

    foreach ($lines as $line) {
      if (!is_array($line)) {
        throw new DomainException('Neplatná položka faktúry.');
      }

      $quantity = (int) ($line['quantity'] ?? 0);
      $unit = (int) ($line['unit_price_cents'] ?? -1);
      $total = (int) ($line['total_cents'] ?? -1);

      if ($quantity < 1 || $unit < 0 || $total !== $quantity * $unit) {
        throw new DomainException('Neplatná cena položky faktúry.');
      }

      $code = (string) ($line['code'] ?? '');
      $guestCount = (int) ($document['guest_count'] ?? 2);
      $isLodging = in_array($code, ['first_night', 'additional_night'], true);
      $isTax = $code === 'local_tax';
      $people = $isLodging || $isTax ? (string) $guestCount : '–';
      $unitCount = $isTax ? (string) ($line['night_count'] ?? (int) ($quantity / max(1, $guestCount))) : (string) $quantity;

      $rows .= '<tr><td>' . self::e((string) ($line['label'] ?? '')) . '</td>'
        . '<td class="number">' . $people . '</td>'
        . '<td class="number">' . $unitCount . '</td>'
        . '<td class="number">' . self::money($unit) . '</td>'
        . '<td class="number">' . self::money($total) . '</td></tr>';
    }

    $guestCount = $document['guest_count'] ?? null;
    $guestInfo = is_int($guestCount) && $guestCount > 0
      ? ' · Počet osôb: ' . $guestCount
      : '';

    $values = [
      '{{logo}}' => 'data:image/jpeg;base64,' . base64_encode($logo),
      '{{number}}' => self::e((string) ($document['number'] ?? '')),
      '{{issued_date}}' => self::date((string) ($document['issued_date'] ?? '')),
      '{{due_date}}' => self::date((string) ($document['due_date'] ?? $document['arrival'] ?? '')),
      '{{stay_dates}}' => self::date((string) $document['arrival']) . ' – ' . self::date((string) $document['departure']),
      '{{supplier}}' => self::party($supplier) . '<br><strong>Miesto pobytu:</strong><br>č. p. 39/3 Závadka<br>053 33 Závadka',
      '{{customer}}' => self::party($customer),
      '{{guest_info}}' => $guestInfo,
      '{{nights}}' => (string) $nights,
      '{{rows}}' => $rows,
      '{{total}}' => self::money((int) ($document['total_cents'] ?? 0)),
    ];

    // The template is Markdown with HTML layout blocks. Only the supported
    // heading subset needs converting; all variable content is escaped above.
    $template = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $template);
    $template = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', (string) $template);

    return '<!DOCTYPE html><html lang="sk"><head><meta charset="UTF-8"><style>'
      . $css . '</style></head><body>' . strtr((string) $template, $values)
      . '</body></html>';
  }

  /**
   * @param array<string, mixed> $party
   * @return string
   */
  private static function party(array $party): string
  {
    $lines = ['<strong>' . self::e((string) ($party['name'] ?? '')) . '</strong>'];
    $address = trim((string) ($party['address'] ?? ''));

    if ($address !== '') {
      $lines[] = nl2br(self::e($address));
    }

    foreach (['ico' => 'IČO', 'dic' => 'DIČ'] as $key => $label) {
      if (!empty($party[$key])) {
        $lines[] = $label . ': ' . self::e((string) $party[$key]);
      }
    }

    return implode('<br>', $lines);
  }

  private static function readAsset(string $path): string
  {
    $content = @file_get_contents($path);

    if ($content === false) {
      throw new DomainException('Chýba súbor šablóny faktúry: ' . basename($path));
    }

    return $content;
  }

  private static function e(string $value): string
  {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  }

  private static function date(string $value): string
  {
    $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

    if ($date === false || $date->format('Y-m-d') !== $value) {
      throw new DomainException('Neplatný dátum faktúry.');
    }

    return $date->format('d. m. Y');
  }

  private static function money(int $cents): string
  {
    return number_format($cents / 100, 2, ',', ' ') . ' €';
  }
}
