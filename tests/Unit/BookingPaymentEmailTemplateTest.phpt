<?php

declare(strict_types=1);

use App\Helpers\PaymentEmailTemplate;
use Tester\Assert;

require dirname(__DIR__) . '/bootstrap.php';

$default = PaymentEmailTemplate::defaults();
Assert::contains('{{variable_symbol}}', $default['body']);
$rendered = PaymentEmailTemplate::content(20, 'Fiktivny prijemca', 'SK0000000000000000000000', null, 12345, '2099000001');
Assert::contains('123.45 EUR', $rendered['body']);
Assert::contains('2099000001', $rendered['body']);
Assert::notContains('BIC:', $rendered['body']);
Assert::contains('<strong>Príjemca:</strong>', $rendered['html']);

$custom = $default;
$custom['subject'] = 'Platba {{booking_id}}';
$custom['body'] = '# Platba\n\n**{{holder}}**\n\n{{iban}}\n\n{{amount}}\n\n{{variable_symbol}}';
$custom['body'] = str_replace('\\n', "\n", $custom['body']);
$sample = PaymentEmailTemplate::content(20, '<script>alert(1)</script>', 'SK0000000000000000000000', null, 12345, '2099000001', $custom);
Assert::same('Platba 20', $sample['subject']);
Assert::notContains('<script>', $sample['html']);
Assert::contains('&lt;script&gt;', $sample['html']);
Assert::contains('<h2>Platba</h2>', $sample['html']);
Assert::exception(
  static function () use ($custom): void {
    $custom['body'] = '{{iban}} {{amount}} {{variable_symbol}}';
    PaymentEmailTemplate::validateTemplate($custom['subject'], $custom['body']);
  },
  InvalidArgumentException::class,
);
Assert::exception(
  static function () use ($custom): void {
    $custom['subject'] = "Bad\r\nBcc: hacker@example.com";
    PaymentEmailTemplate::validateTemplate($custom['subject'], $custom['body']);
  },
  InvalidArgumentException::class,
);
