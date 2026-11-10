<?php

declare(strict_types=1);

namespace App\Model;

use Nette\Mail\Message;
use RuntimeException;

/**
 * Sends bank transfer details via the project's existing SMTP credentials.
 * Never generates a QR or contacts the PAY by square API.
 */
final class BookingPaymentInstructionMailer
{
  /**
   * @return bool Whether outgoing mail has the required configuration.
   */
  public static function configured(): bool
  {
    return SmtpTransportFactory::configured();
  }

  /**
   * @param int $bookingId Permanent reservation identifier.
   * @param string $recipient Validated guest email address.
   * @param string $holder Bank account holder.
   * @param string $iban Receiving IBAN.
   * @param string|null $bic Optional BIC.
   * @param int $cents Total price in eurocents.
   * @param string $variableSymbol Stable payment reference.
   * @param string|null $png Cached PAY by square PNG; no API generation.
   * @return void
   */
  public function send(
    int $bookingId,
    string $recipient,
    string $holder,
    string $iban,
    ?string $bic,
    int $cents,
    string $variableSymbol,
    ?string $png,
  ): void {
    if (!self::configured()) {
      throw new RuntimeException('SMTP nie je nakonfigurované.');
    }
    if ($bookingId < 1 || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false
      || $cents <= 0 || preg_match('/^[0-9]{10}$/D', $variableSymbol) !== 1) {
      throw new RuntimeException('Neplatné platobné údaje pre e-mail.');
    }
    $iban = BookingPaymentDetails::normalizeIban($iban);
    $holder = trim($holder);
    if ($holder === '' || strpbrk($holder, "\r\n") !== false) {
      throw new RuntimeException('Neplatný príjemca platby.');
    }
    $mail = new Message();
    $mail->setFrom((string) getenv('MAIL_FROM'))
      ->addTo($recipient)
      ->setSubject('Platobné údaje k rezervácii č. ' . $bookingId)
      ->setBody(self::body($bookingId, $holder, $iban, $bic, $cents, $variableSymbol));
    if ($png !== null) {
      $mail->addAttachment('platba-' . $bookingId . '-pay-by-square.png', $png, 'image/png');
    }
    $mailer = SmtpTransportFactory::create(15);
    $mailer->send($mail);
  }

  /**
   * Builds plain text without inserting user-supplied HTML.
   *
   * @return string Plain-text payment instructions.
   */
  public static function body(
    int $bookingId,
    string $holder,
    string $iban,
    ?string $bic,
    int $cents,
    string $variableSymbol,
  ): string {
    return "Dobrý deň,\n\nzasielame platobné údaje k potvrdenej rezervácii č. " . $bookingId
      . "\nPríjemca: " . $holder
      . "\nIBAN: " . $iban
      . ($bic !== null && $bic !== '' ? "\nBIC: " . $bic : '')
      . "\nSuma: " . BookingPaymentDetails::amount($cents) . " EUR"
      . "\nVariabilný symbol: " . $variableSymbol
      . "\nSpráva pre príjemcu: Rezervacia VS " . $variableSymbol
      . "\n\nPlatbu odošlite bankovým prevodom na uvedený účet."
      . "\nAk váš bankový klient podporuje PAY by square, môžete použiť priložený QR kód (ak je priložený)."
      . "\nPred potvrdením platby skontrolujte údaje.\n\nĎakujeme.\n";
  }
}
