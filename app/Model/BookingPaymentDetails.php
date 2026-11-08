<?php

declare(strict_types=1);

namespace App\Model;

use InvalidArgumentException;

/**
 * Validates bank-transfer details without storing personal or bank data in code.
 */
final class BookingPaymentDetails
{
  /**
   * Normalizes and validates a SEPA IBAN using the ISO 13616 mod-97 check.
   *
   * @param string $iban Bank account entered by the administrator.
   * @return string Canonical uppercase IBAN without whitespace.
   */
  public static function normalizeIban(string $iban): string
  {
    $normalized = strtoupper(preg_replace('/\s+/', '', $iban) ?? '');
    if (!preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/D', $normalized)) {
      throw new InvalidArgumentException('IBAN musí obsahovať 15 až 34 znakov vo formáte ISO 13616.');
    }
    // Slovakia has a fixed 24-character IBAN.
    if (str_starts_with($normalized, 'SK') && strlen($normalized) !== 24) {
      throw new InvalidArgumentException('Slovenský IBAN musí mať presne 24 znakov.');
    }
    $rearranged = substr($normalized, 4) . substr($normalized, 0, 4);
    $remainder = 0;
    for ($i = 0, $length = strlen($rearranged); $i < $length; $i++) {
      $char = $rearranged[$i];
      $number = ctype_digit($char) ? $char : (string) (ord($char) - 55);
      for ($j = 0, $digits = strlen($number); $j < $digits; $j++) {
        $remainder = ($remainder * 10 + (int) $number[$j]) % 97;
      }
    }
    if ($remainder !== 1) {
      throw new InvalidArgumentException('Kontrolné číslice IBAN nie sú platné.');
    }
    return $normalized;
  }

  /**
   * Creates a stable, unique variable symbol from the booking ID.
   *
   * @param int $bookingId Database booking identifier.
   * @return string Variable symbol with ten digits.
   */
  public static function variableSymbol(int $bookingId): string
  {
    if ($bookingId < 1 || $bookingId > 9999999999) {
      throw new InvalidArgumentException('Číslo rezervácie nie je možné previesť na variabilný symbol.');
    }
    return str_pad((string) $bookingId, 10, '0', STR_PAD_LEFT);
  }

  /**
   * Converts saved eurocents to bank-transfer amount without floating point.
   *
   * @param int $amountCents Nonnegative quoted booking total in cents.
   * @return string Amount in EUR with two decimal places.
   */
  public static function amount(int $amountCents): string
  {
    if ($amountCents < 0) {
      throw new InvalidArgumentException('Suma platby nesmie byť záporná.');
    }
    return intdiv($amountCents, 100) . '.' . str_pad((string) ($amountCents % 100), 2, '0', STR_PAD_LEFT);
  }
}
