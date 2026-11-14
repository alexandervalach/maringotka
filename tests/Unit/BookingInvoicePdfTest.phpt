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
    ['label' => 'Ubytovanie', 'quantity' => 1, 'unit_price_cents' => 12000, 'total_cents' => 12000],
  ],
  'total_cents' => 12000,
];

$html = (new InvoicePdf())->html($document);

Assert::contains('2026-11-F000001', $html);
Assert::contains('120,00 €', $html);
Assert::contains('&lt;script&gt;', $html);
Assert::notContains('<script>', $html);
Assert::contains('ODBERATEĽ', $html);
Assert::notContains('Dodávateľ nie je platiteľom DPH.', $html);

Assert::contains('data:image/jpeg;base64,', $html);
Assert::contains('INFORMÁCIE O POBYTE', $html);
Assert::contains('Miesto pobytu:', $html);
Assert::contains('Počet nocí: 1', $html);
Assert::contains('Dátum splatnosti:', $html);
Assert::contains('CELKOM (€):', $html);
Assert::notContains('Dodávateľ nie je platiteľom DPH.', $html);

Assert::contains('<h1>FAKTÚRA</h1>', $html);
Assert::contains('<h2>DODÁVATEĽ</h2>', $html);
Assert::notContains('{{customer}}', $html);
Assert::notContains('{{rows}}', $html);
