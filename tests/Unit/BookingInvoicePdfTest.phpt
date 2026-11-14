<?php

declare(strict_types=1);

use App\Components\Invoice\InvoicePdf;
use Tester\Assert;

require dirname(__DIR__) . '/bootstrap.php';

$document = [
  'number' => '2026-11-F000001',
  'issued_date' => '2026-11-14',
  'payment_recorded_date' => '2026-11-13',
  'arrival' => '2026-11-21',
  'departure' => '2026-11-22',
  'supplier' => [
    'name' => 'Testovacia prevádzka',
    'address' => 'Fiktívna 1, Testovacie mesto',
    'ico' => '00000000',
    'dic' => '',
  ],
  'customer' => [
    'name' => '<script>alert(1)</script>',
    'address' => '',
    'ico' => '',
    'dic' => '',
  ],
  'lines' => [
    ['code' => 'first_night', 'label' => 'Ubytovanie', 'quantity' => 1, 'unit_price_cents' => 11600, 'total_cents' => 11600],
    ['code' => 'local_tax', 'label' => 'Miestny poplatok', 'quantity' => 2, 'unit_price_cents' => 200, 'total_cents' => 400, 'night_count' => 1],
  ],
  'guest_count' => 2,
  'total_cents' => 12000,
];

$html = (new InvoicePdf())->html($document);

Assert::contains('2026-11-F000001', $html);
Assert::contains('120,00 €', $html);
Assert::contains('116,00 €', $html);
Assert::contains('4,00 €', $html);
Assert::contains('Miestny poplatok', $html);
Assert::contains('Počet osôb', $html);
Assert::contains('&lt;script&gt;', $html);
Assert::notContains('<script>', $html);
Assert::contains('ODBERATEĽ', $html);
Assert::notContains('Dodávateľ nie je platiteľom DPH.', $html);

Assert::contains('data:image/jpeg;base64,', $html);
Assert::contains('INFORMÁCIE O POBYTE', $html);
Assert::contains('Miesto pobytu:', $html);
Assert::contains('Počet nocí: 1', $html);
Assert::contains('Dátum splatnosti:', $html);
Assert::contains('<td class="meta-value">21. 11. 2026</td>', $html);
Assert::notContains('Typ ubytovania:', $html);
Assert::contains('CELKOM (€):', $html);
Assert::notContains('Dodávateľ nie je platiteľom DPH.', $html);

Assert::contains('<h1>FAKTÚRA</h1>', $html);
Assert::contains('<h2>DODÁVATEĽ</h2>', $html);
Assert::notContains('{{customer}}', $html);
Assert::notContains('{{rows}}', $html);
