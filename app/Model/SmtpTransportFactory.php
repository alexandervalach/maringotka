<?php

declare(strict_types=1);

namespace App\Model;

use Nette\Mail\SmtpMailer;
use RuntimeException;

/**
 * Validates the shared SMTP environment and creates an SMTP transport.
 * Does not send messages or depend on booking-specific code.
 */
final class SmtpTransportFactory
{
  /**
   * @return bool Whether the transport and sender are configured correctly.
   */
  public static function configured(): bool
  {
    try {
      self::configuration();
      return true;
    } catch (RuntimeException) {
      return false;
    }
  }

  /**
   * @param int $timeout SMTP connection timeout in seconds.
   * @return SmtpMailer A configured transport; no connection is made here.
   */
  public static function create(int $timeout = 20): SmtpMailer
  {
    if ($timeout < 1) {
      throw new RuntimeException('Neplatný SMTP timeout.');
    }
    $settings = self::configuration();
    return new SmtpMailer(
      host: $settings['host'],
      username: $settings['username'],
      password: $settings['password'],
      port: $settings['port'],
      encryption: $settings['encryption'],
      timeout: $timeout,
    );
  }

  /**
   * @return array{host: string, username: string, password: string, port: int, encryption: string}
   */
  private static function configuration(): array
  {
    $values = [];
    foreach (['SMTP_HOST', 'SMTP_USER', 'SMTP_PASSWORD', 'SMTP_PORT', 'SMTP_ENCRYPTION', 'MAIL_FROM'] as $name) {
      $value = getenv($name);
      if (!is_string($value) || trim($value) === '') {
        throw new RuntimeException('Chýba konfigurácia ' . $name . '.');
      }
      $values[$name] = $value;
    }
    if (filter_var($values['MAIL_FROM'], FILTER_VALIDATE_EMAIL) === false) {
      throw new RuntimeException('Neplatná odosielacia e-mailová adresa.');
    }
    $port = filter_var($values['SMTP_PORT'], FILTER_VALIDATE_INT);
    if (!is_int($port) || $port < 1 || $port > 65535) {
      throw new RuntimeException('Neplatný SMTP port.');
    }
    if (!in_array($values['SMTP_ENCRYPTION'], ['ssl', 'tls'], true)) {
      throw new RuntimeException('Neplatné SMTP šifrovanie.');
    }
    return [
      'host' => $values['SMTP_HOST'],
      'username' => $values['SMTP_USER'],
      'password' => $values['SMTP_PASSWORD'],
      'port' => $port,
      'encryption' => $values['SMTP_ENCRYPTION'],
    ];
  }
}
