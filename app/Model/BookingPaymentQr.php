<?php

declare(strict_types=1);

namespace App\Model;

use InvalidArgumentException;
use RuntimeException;

/**
 * Generates and caches official PAY by square PNGs on the server.
 * QR images are never requested directly from a visitor's browser.
 */
final class BookingPaymentQr
{
  private const ENDPOINT = 'https://api.bysquare.com/generate/pay?formats=pay';
  private const PNG_SIGNATURE = "\x89PNG\r\n\x1a\n";

  /**
   * @param string $cacheDirectory Non-public writable directory.
   */
  public function __construct(private readonly string $cacheDirectory)
  {
  }

  /**
   * @param string $iban Beneficiary IBAN.
   * @param string $holder Beneficiary name.
   * @param int $cents Amount in eurocents.
   * @param string $variableSymbol Ten-digit payment reference.
   * @return array{payments: list<array<string, mixed>>} Official API document.
   */
  public static function document(string $iban, string $holder, int $cents, string $variableSymbol): array
  {
    $iban = BookingPaymentDetails::normalizeIban($iban);
    $holder = trim($holder);
    if ($holder === '' || mb_strlen($holder) > 70 || strpbrk($holder, "\r\n") !== false) {
      throw new InvalidArgumentException('Neplatný príjemca platby.');
    }
    if ($cents < 1 || $cents > 99999999999) {
      throw new InvalidArgumentException('Neplatná suma platby.');
    }
    if (preg_match('/^[0-9]{10}$/D', $variableSymbol) !== 1) {
      throw new InvalidArgumentException('Variabilný symbol musí mať desať číslic.');
    }

    return [
      'payments' => [[
        'amount' => (float) BookingPaymentDetails::amount($cents),
        'currencyCode' => 'EUR',
        'bankAccounts' => [['iban' => $iban]],
        'beneficiaryName' => $holder,
        'paymentNote' => 'Rezervacia VS ' . $variableSymbol,
        'variableSymbol' => $variableSymbol,
      ]],
    ];
  }

  /**
   * Reads an existing QR without using any generation credit.
   * Changing the account, amount or VS naturally changes the cache key.
   *
   * @param array{payments: list<array<string, mixed>>} $document Validated document.
   * @return string|null Verified PNG bytes, or null if not yet generated.
   */
  public function cached(array $document): ?string
  {
    $path = $this->cachePath($document);
    if (!is_file($path)) {
      return null;
    }
    $bytes = file_get_contents($path);
    return is_string($bytes) && $this->validPng($bytes) ? $bytes : null;
  }

  /**
   * Generates at most once per unique document, including concurrent requests.
   * Never logs or exposes the API key or the remote response body.
   *
   * @param array{payments: list<array<string, mixed>>} $document Validated document.
   * @param string $apiKey Secret from the server environment.
   * @return string Verified PNG bytes.
   */
  public function generate(array $document, string $apiKey): string
  {
    if (trim($apiKey) === '' || strpbrk($apiKey, "\r\n") !== false) {
      throw new RuntimeException('PAY by square nie je nakonfigurovaný.');
    }
    if (!is_dir($this->cacheDirectory) && !mkdir($this->cacheDirectory, 0700, true) && !is_dir($this->cacheDirectory)) {
      throw new RuntimeException('Nie je možné vytvoriť cache QR kódov.');
    }
    $path = $this->cachePath($document);
    $lock = fopen($path . '.lock', 'c');
    if ($lock === false) {
      throw new RuntimeException('Nie je možné uzamknúť cache QR kódov.');
    }
    try {
      if (!flock($lock, LOCK_EX)) {
        throw new RuntimeException('Nie je možné uzamknúť cache QR kódov.');
      }
      $cached = $this->cached($document);
      if ($cached !== null) {
        return $cached;
      }
      $png = $this->requestPng($document, $apiKey);
      $temp = tempnam($this->cacheDirectory, 'qr-');
      if ($temp === false) {
        throw new RuntimeException('Nie je možné uložiť QR kód.');
      }
      try {
        if (file_put_contents($temp, $png) !== strlen($png) || !rename($temp, $path)) {
          throw new RuntimeException('Nie je možné uložiť QR kód.');
        }
      } finally {
        if (is_file($temp)) {
          unlink($temp);
        }
      }
      return $png;
    } finally {
      flock($lock, LOCK_UN);
      fclose($lock);
    }
  }

  /**
   * @param array{payments: list<array<string, mixed>>} $document Official API document.
   * @param string $apiKey Server-side API key.
   * @return string PNG bytes.
   */
  private function requestPng(array $document, string $apiKey): string
  {
    $body = json_encode($document, JSON_THROW_ON_ERROR);
    $context = stream_context_create([
      'http' => [
        'method' => 'POST',
        'header' => "Authorization: " . $apiKey . "\r\nContent-Type: application/json\r\nAccept: application/json\r\n",
        'content' => $body,
        'timeout' => 8,
        'ignore_errors' => true,
        'follow_location' => 0,
      ],
      'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);
    // Limit the response size; the service returns a small JSON document.
    $response = @file_get_contents(self::ENDPOINT, false, $context, 0, 1048576);
    $headers = http_get_last_response_headers();
    if (!is_string($response) || !isset($headers[0]) || !preg_match('~^HTTP/\S+ 200(?:\s|$)~', $headers[0])) {
      throw new RuntimeException('Služba by square nevrátila platný QR kód. Skontrolujte API kľúč a kredit.');
    }
    $result = json_decode($response, true);
    if (!is_array($result)
      || ($result['contentType'] ?? null) !== 'image/png'
      || ($result['format'] ?? null) !== 'pay'
      || !isset($result['image']) || !is_string($result['image'])) {
      throw new RuntimeException('Služba by square vrátila neočakávaný formát.');
    }
    $png = base64_decode($result['image'], true);
    if (!is_string($png) || !$this->validPng($png)) {
      throw new RuntimeException('Služba by square vrátila neplatný obrázok.');
    }
    return $png;
  }

  /**
   * @param array{payments: list<array<string, mixed>>} $document Payment document.
   * @return string Deterministic private cache filename.
   */
  private function cachePath(array $document): string
  {
    $json = json_encode($document, JSON_THROW_ON_ERROR);
    return rtrim($this->cacheDirectory, '/\\') . '/pay-' . hash('sha256', $json) . '.png';
  }

  /**
   * @param string $png Image bytes.
   * @return bool Whether the response looks like a nontrivial PNG.
   */
  private function validPng(string $png): bool
  {
    return strlen($png) >= 67 && strlen($png) <= 750000 && str_starts_with($png, self::PNG_SIGNATURE);
  }
}
