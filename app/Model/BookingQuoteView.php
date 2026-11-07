<?php

declare(strict_types=1);

namespace App\Model;

use InvalidArgumentException;
use JsonException;

/**
 * Validates and formats the immutable price snapshot for administrator views.
 */
final class BookingQuoteView
{
  /**
   * @var array<string, array{label: string, symbol: string}>
   */
  private const ITEMS = [
    'first_night' => ['label' => 'Prvá noc', 'symbol' => '⌂'],
    'additional_night' => ['label' => 'Ďalšie noci', 'symbol' => '⌂'],
    'wellness' => ['label' => 'Wellness', 'symbol' => '♨'],
    'breakfast' => ['label' => 'Raňajkový kôš', 'symbol' => '☕'],
    'romance' => ['label' => 'Romantické prekvapenie', 'symbol' => '♥'],
    'frizzante' => ['label' => 'Tokajské Frizzante', 'symbol' => '◇'],
    'canvas_bag' => ['label' => 'Plátená taška', 'symbol' => '▣'],
  ];

  /**
   * Normalizes the stored price quote without recalculating historical prices.
   * NULL values represent an older booking that predates price snapshots.
   * A malformed snapshot is reported as invalid rather than silently ignored.
   *
   * @param mixed $storedQuote JSONB value returned by Nette Database.
   * @param mixed $storedTotal Total price in cents.
   * @return array{total_cents: int, nights: int, lines: list<array{code: string, label: string, symbol: string, quantity: int, unit_price_cents: int, total_cents: int, unit: string, extra: bool}>}|null
   */
  public function parse(mixed $storedQuote, mixed $storedTotal): ?array
  {
    if ($storedQuote === null && $storedTotal === null) {
      return null;
    }
    if (!is_string($storedQuote) && !is_array($storedQuote)) {
      throw new InvalidArgumentException('Neplatný cenový rozpis rezervácie.');
    }
    if (is_string($storedQuote)) {
      try {
        $quote = json_decode($storedQuote, true, 512, JSON_THROW_ON_ERROR);
      } catch (JsonException $error) {
        throw new InvalidArgumentException('Neplatný JSON cenového rozpisu.', 0, $error);
      }
    } else {
      $quote = $storedQuote;
    }
    if (!is_array($quote) || !isset($quote['items'], $quote['total_cents'], $quote['nights'])
      || !is_array($quote['items']) || !is_int($quote['total_cents']) || !is_int($quote['nights'])
      || $quote['nights'] < 1 || $quote['nights'] > 30
      || (!is_int($storedTotal) && !(is_string($storedTotal) && ctype_digit($storedTotal)))
      || (int) $storedTotal !== $quote['total_cents']) {
      throw new InvalidArgumentException('Nekonzistentný cenový rozpis rezervácie.');
    }

    $lines = [];
    $sum = 0;
    foreach ($quote['items'] as $item) {
      if (!is_array($item) || !isset($item['code'], $item['quantity'], $item['unit_price_cents'], $item['total_cents'], $item['unit'])
        || !is_string($item['code']) || !isset(self::ITEMS[$item['code']])
        || !is_int($item['quantity']) || $item['quantity'] < 1
        || !is_int($item['unit_price_cents']) || $item['unit_price_cents'] < 0
        || !is_int($item['total_cents']) || $item['total_cents'] < 0
        || !is_string($item['unit']) || !in_array($item['unit'], ['night', 'stay', 'item'], true)
        || $item['quantity'] * $item['unit_price_cents'] !== $item['total_cents']) {
        throw new InvalidArgumentException('Neplatná položka cenového rozpisu.');
      }
      $code = $item['code'];
      $sum += $item['total_cents'];
      $lines[] = [
        'code' => $code,
        'label' => self::ITEMS[$code]['label'],
        'symbol' => self::ITEMS[$code]['symbol'],
        'quantity' => $item['quantity'],
        'unit_price_cents' => $item['unit_price_cents'],
        'total_cents' => $item['total_cents'],
        'unit' => $item['unit'],
        'extra' => !in_array($code, ['first_night', 'additional_night'], true),
      ];
    }
    if ($sum !== $quote['total_cents']) {
      throw new InvalidArgumentException('Súčet položiek sa nezhoduje s cenou rezervácie.');
    }
    return ['total_cents' => $sum, 'nights' => $quote['nights'], 'lines' => $lines];
  }

  /**
   * @param int $cents Nonnegative price in eurocents.
   * @return string Localized formatted price.
   */
  public static function euros(int $cents): string
  {
    return number_format($cents / 100, 2, ',', ' ') . ' €';
  }
}
