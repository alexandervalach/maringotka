<?php

declare(strict_types=1);

namespace App\Model;

use App\Helpers\PaymentEmailTemplate;
use Nette\Mail\Message;
use RuntimeException;

/**
 * Sends prepared payment instructions using the existing SMTP infrastructure.
 */
final class BookingPaymentInstructionMailer
{
  /**
   * @return bool Whether SMTP is configured.
   */
  public static function configured(): bool
  {
    return SmtpTransportFactory::configured();
  }

  /**
   * @param int $bookingId Permanent reservation identifier.
   * @param string $recipient Guest email.
   * @param string $holder Account holder.
   * @param string $iban Bank account.
   * @param string|null $bic Optional BIC.
   * @param int $cents Price in eurocents.
   * @param string $variableSymbol Payment reference.
   * @param string|null $png Cached QR attachment.
   * @param array{subject: string, body: string, show_logo: bool}|null $template Saved template.
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
    ?array $template = null,
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
    $content = PaymentEmailTemplate::content($bookingId, $holder, $iban, $bic, $cents, $variableSymbol, $template);
    $logoPath = dirname(__DIR__, 2) . '/www/assets/images/logo.png';
    $mail = new Message();
    $mail->setFrom((string) getenv('MAIL_FROM'))
      ->addTo($recipient)
      ->setSubject($content['subject'])
      ->setHtmlBody(
        PaymentEmailTemplate::htmlDocument($content['html'], $content['show_logo'], is_file($logoPath)),
        dirname($logoPath),
      );
    if ($png !== null) {
      $mail->addAttachment('platba-' . $bookingId . '-pay-by-square.png', $png, 'image/png');
    }
    SmtpTransportFactory::create(15)->send($mail);
  }
}
