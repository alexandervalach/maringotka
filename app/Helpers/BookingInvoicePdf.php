<?php

declare(strict_types=1);

namespace App\Helpers;

use Dompdf\Dompdf;
use Dompdf\Options;
use DomainException;

/**
 * Stateless HTML rendering of a previously issued immutable invoice snapshot.
 * Never fetches remote assets and never stores customer PDFs on disk.
 */
final class BookingInvoicePdf
{
  /**
   * @param array<string, mixed> $document Validated issued invoice snapshot.
   * @return string PDF bytes.
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
   * Builds printable HTML from escaped values only.
   *
   * @param array<string, mixed> $document
   * @return string
   */
  public function html(array $document): string
  {
    $supplier = $document['supplier'] ?? [];
    $customer = $document['customer'] ?? [];

    if (!is_array($supplier) || !is_array($customer) || !is_array($document['lines'] ?? null)) {
      throw new DomainException('Neplatné údaje faktúry.');
    }

    $supplierHtml = self::party($supplier);
    $customerHtml = self::party($customer);
    $rows = '';

    foreach ($document['lines'] as $line) {
      if (!is_array($line)) {
        throw new DomainException('Neplatná položka faktúry.');
      }

      $quantity = (int) ($line['quantity'] ?? 0);
      $unit = (int) ($line['unit_price_cents'] ?? -1);
      $total = (int) ($line['total_cents'] ?? -1);

      if ($quantity < 1 || $unit < 0 || $total !== $quantity * $unit) {
        throw new DomainException('Neplatná cena položky faktúry.');
      }

      $rows .= '<tr><td>' . self::e((string) ($line['label'] ?? '')) . '</td>'
        . '<td class="number">' . $quantity . '</td>'
        . '<td class="number">' . self::money($unit) . '</td>'
        . '<td class="number">' . self::money($total) . '</td></tr>';
    }

    return '<!DOCTYPE html><html lang="sk"><head><meta charset="UTF-8"><style>'
      . '@page{margin:38px 42px}body{font-family:"DejaVu Sans",sans-serif;font-size:10px;color:#253b30}'
      . '.top{border-bottom:2px solid #203b2e;padding-bottom:16px;margin-bottom:24px}'
      . 'h1{font-size:27px;margin:0;color:#203b2e}h2{font-size:12px;margin:0 0 8px}'
      . '.subtitle{color:#718077;font-size:10px;margin-top:5px}'
      . '.parties{width:100%;margin:24px 0;border-spacing:0}.parties td{width:50%;vertical-align:top;padding:0 24px 0 0}'
      . '.details{width:100%;border-spacing:0;margin:0 0 18px}.details td{padding:5px 10px 5px 0}'
      . '.muted{color:#67776d}.items{width:100%;border-collapse:collapse;table-layout:fixed}'
      . '.items th{text-align:left;padding:10px 7px;background:#eef0e9;font-size:9px}'
      . '.items td{padding:9px 7px;border-bottom:1px solid #e3e4dc}'
      . '.items th:first-child,.items td:first-child{width:44%}'
      . '.number{text-align:right}.total{text-align:right;background:#eef0e9;padding:14px;font-weight:bold;font-size:15px;margin-top:12px}'
      . '.paid{display:inline-block;padding:7px 12px;background:#e4efe6;color:#225438;border-radius:5px;font-weight:bold}'
      . '.footer{margin-top:30px;padding-top:16px;border-top:1px solid #e0e4dd;color:#66776b;font-size:9px}'
      . '</style></head><body>'
      . '<div class="top"><h1>FAKTÚRA</h1><div class="subtitle">Maringotka na Závadke · '
      . self::e((string) $document['number']) . '</div></div>'
      . '<table class="details"><tr><td><strong>Číslo faktúry:</strong> '
      . self::e((string) $document['number']) . '</td><td><strong>Vystavená:</strong> '
      . self::date((string) $document['issued_date']) . '</td></tr>'
      . '<tr><td><strong>Termín pobytu:</strong> '
      . self::date((string) $document['arrival']) . ' – ' . self::date((string) $document['departure'])
      . '</td><td><strong>Úhrada evidovaná:</strong> '
      . self::date((string) $document['payment_recorded_date']) . '</td></tr></table>'
      . '<table class="parties"><tr><td><h2>DODÁVATEĽ</h2>' . $supplierHtml
      . '</td><td><h2>ODBERATEĽ</h2>' . $customerHtml . '</td></tr></table>'
      . '<h2>Poskytnuté služby a objednané položky</h2>'
      . '<table class="items"><thead><tr><th>Položka</th><th class="number">Počet</th>'
      . '<th class="number">Jednotková cena</th><th class="number">Spolu</th></tr></thead>'
      . '<tbody>' . $rows . '</tbody></table>'
      . '<div class="total">CELKOM: ' . self::money((int) $document['total_cents']) . '</div>'
      . '<p><span class="paid">UHRADENÉ</span></p>'
      . '<div class="footer">Ďakujeme za návštevu Maringotky na Závadke.</div>'
      . '</body></html>';
  }

  /**
   * @param array<string, mixed> $party
   */
  private static function party(array $party): string
  {
    $lines = ['<strong>' . self::e((string) ($party['name'] ?? '')) . '</strong>'];
    $lines[] = nl2br(self::e((string) ($party['address'] ?? '')));

    foreach (['ico' => 'IČO', 'dic' => 'DIČ'] as $key => $label) {
      if (!empty($party[$key])) {
        $lines[] = $label . ': ' . self::e((string) $party[$key]);
      }
    }

    return implode('<br>', $lines);
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
