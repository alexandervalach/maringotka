<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Model\BookingPaymentQr;
use Tester\Assert;

$document = BookingPaymentQr::document('SK31 1200 0000 1987 4263 7541', 'Test Prijemca', 29000, '2026000020');
Assert::same('SK3112000000198742637541', $document['payments'][0]['bankAccounts'][0]['iban']);
Assert::same('2026000020', $document['payments'][0]['variableSymbol']);
Assert::same('EUR', $document['payments'][0]['currencyCode']);
Assert::same(290.0, $document['payments'][0]['amount']);
Assert::same('Rezervacia VS 2026000020', $document['payments'][0]['paymentNote']);
Assert::exception(static fn () => BookingPaymentQr::document('SK3112000000198742637541', 'Test', 0, '2026000020'), InvalidArgumentException::class);
Assert::exception(static fn () => BookingPaymentQr::document('SK3112000000198742637541', "Invalid\nName", 100, '2026000020'), InvalidArgumentException::class);
Assert::exception(static fn () => BookingPaymentQr::document('SK3112000000198742637541', 'Test', 100, '20'), InvalidArgumentException::class);

$qr = new BookingPaymentQr(sys_get_temp_dir() . '/maringotka-qr-nonexistent-' . getmypid());
Assert::null($qr->cached($document));
Assert::exception(static fn () => $qr->generate($document, ''), RuntimeException::class);
