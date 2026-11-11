<?php

declare(strict_types=1);

use App\Helpers\PaymentEmailTemplate;
use Tester\Assert;

require dirname(__DIR__) . '/bootstrap.php';

$body = PaymentEmailTemplate::body(
  20,
  'Fiktivny prijemca',
  'SK0000000000000000000000',
  null,
  12345,
  '2099000001',
);
Assert::contains('123.45 EUR', $body);
Assert::contains('2099000001', $body);
Assert::contains('SK0000000000000000000000', $body);
Assert::notContains('BIC:', $body);
Assert::contains('BIC: TESTSKXX', PaymentEmailTemplate::body(20, 'Fiktivny prijemca', 'SK0000000000000000000000', 'TESTSKXX', 12345, '2099000001'));
