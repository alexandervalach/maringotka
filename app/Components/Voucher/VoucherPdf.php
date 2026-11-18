<?php

declare(strict_types=1);

namespace App\Components\Voucher;

use Dompdf\Dompdf;
use Dompdf\Options;
use DomainException;

/**
 * Generates a gift voucher PDF from an external Markdown and CSS template.
 */
final class VoucherPdf
{
  /**
   * @param array<string, mixed> $document
   * @return string
   */
  public function render(array $document): string
  {
    if (!class_exists(Dompdf::class)) {
      throw new DomainException('Chýba knižnica dompdf/dompdf.');
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
    $template = file_get_contents(__DIR__ . '/voucher.md');
    $css = file_get_contents(__DIR__ . '/voucher.css');

    if ($template === false || $css === false) {
      throw new DomainException('Chýba šablóna darčekového poukazu.');
    }

    $code = (string) ($document['short_code'] ?? '');

    // Previously issued six-character aliases remain valid. New ones have eight characters.
    if (preg_match('/^[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{6}(?:[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{2})?$/D', $code) !== 1) {
      throw new DomainException('Chýba platný krátky kód darčekového poukazu.');
    }

    $logo = file_get_contents(__DIR__ . '/assets/voucher-logo.jpg');

    if ($logo === false) {
      throw new DomainException('Chýba logo darčekového poukazu.');
    }

    $photo = file_get_contents(__DIR__ . '/assets/voucher-photo.jpg');

    if ($photo === false) {
      throw new DomainException('Chýba fotografia darčekového poukazu.');
    }

    $nights = (int) ($document['nights'] ?? 0);
    $months = (int) ($document['validity_months'] ?? 0);
    $cents = (int) ($document['price_cents'] ?? -1);

    if ($nights < 1 || $nights > 30 || $months < 1 || $months > 36 || $cents < 100) {
      throw new DomainException('Neplatné údaje poukazu.');
    }

    $benefits = '<li>' . ($nights === 1 ? 'Pobyt na jednu noc' : 'Pobyt na ' . $nights . ' nocí') . '</li>';

    if (!empty($document['includes_wellness'])) {
      $benefits .= '<li>Súkromný wellness – sauna a kúpacia kaďa – v cene</li>';
    }

    if (!empty($document['includes_wine'])) {
      $benefits .= '<li>Fľaša vína v cene</li>';
    }

    $benefits .= '<li>Platnosť ' . $months . ' ' . ($months === 1 ? 'mesiac' : ($months < 5 ? 'mesiace' : 'mesiacov')) . '</li>';

    $values = [
      '{{logo}}' => 'data:image/jpeg;base64,' . base64_encode($logo),
      '{{photo}}' => 'data:image/jpeg;base64,' . base64_encode($photo),
      '{{campaign}}' => self::escape((string) ($document['name'] ?? '')),
      '{{price}}' => number_format($cents / 100, 2, ',', ' ') . ' €',
      '{{code}}' => $code,
      '{{benefits}}' => $benefits,
      '{{issued}}' => self::date((string) ($document['issued_date'] ?? '')),
      '{{expires}}' => self::date((string) ($document['expires_date'] ?? '')),
    ];

    $template = preg_replace('/^\s*# (.+)$/m', '<h1>$1</h1>', $template);
    $template = preg_replace('/^\s*## (.+)$/m', '<h2>$1</h2>', (string) $template);

    return '<!DOCTYPE html><html lang="sk"><head><meta charset="UTF-8"><style>'
      . $css . '</style></head><body>' . strtr((string) $template, $values)
      . '</body></html>';
  }

  private static function escape(string $value): string
  {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  }

  private static function date(string $date): string
  {
    $value = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);

    if ($value === false || $value->format('Y-m-d') !== $date) {
      throw new DomainException('Neplatný dátum poukazu.');
    }

    return $value->format('d. m. Y');
  }
}
