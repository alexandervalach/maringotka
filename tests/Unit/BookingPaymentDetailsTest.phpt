<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Model\BookingPaymentDetails;
use Tester\Assert;

Assert::same('SK3112000000198742637541', BookingPaymentDetails::normalizeIban('sk31 1200 0000 1987 4263 7541'));
Assert::same('GB82WEST12345698765432', BookingPaymentDetails::normalizeIban('GB82 WEST 1234 5698 7654 32'));
Assert::exception(static fn () => BookingPaymentDetails::normalizeIban('SK0012000000198742637541'), InvalidArgumentException::class);
Assert::exception(static fn () => BookingPaymentDetails::normalizeIban('SK31'), InvalidArgumentException::class);
Assert::same('2026000020', BookingPaymentDetails::variableSymbol(20, 2026));
Assert::same('2027000020', BookingPaymentDetails::variableSymbol(20, 2027));
Assert::same('2026999999', BookingPaymentDetails::variableSymbol(999999, 2026));
Assert::exception(static fn () => BookingPaymentDetails::variableSymbol(0, 2026), InvalidArgumentException::class);
Assert::exception(static fn () => BookingPaymentDetails::variableSymbol(1000000, 2026), InvalidArgumentException::class);
Assert::exception(static fn () => BookingPaymentDetails::variableSymbol(20, 1999), InvalidArgumentException::class);
Assert::same('200.00', BookingPaymentDetails::amount(20000));
Assert::same('0.05', BookingPaymentDetails::amount(5));
Assert::exception(static fn () => BookingPaymentDetails::amount(-1), InvalidArgumentException::class);
