<?php

declare(strict_types=1);

namespace App\Model;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Calculates an indicative booking quote using exact integer eurocents.
 * This service does not save bookings or change the live price list.
 */
final class BookingPriceCalculator
{
  /**
   * Current public catalogue prices, until products are stored in pricing data.
   *
   * @var array{frizzante: int, canvas_bag: int}
   */
  public const PRODUCT_PRICES = [
    'frizzante' => 1000,
    'canvas_bag' => 500,
  ];

  /**
   * Calculate a quote from authoritative rate data and selected services.
   * Wellness and breakfast are charged for every night of the stay when selected.
   * Romance is charged once; wine and bags are charged per item.
   * The seasonal and package pricing rules are deliberately not invented here.
   *
   * @param string $arrival Date in YYYY-MM-DD format.
   * @param string $departure Date in YYYY-MM-DD format; checkout is exclusive.
   * @param array{first_night: int, additional_night: int, wellness: int, breakfast: int, romance: int, minimum_nights: int} $prices Current PricingRepository::getPrices() values, in cents.
   * @param array<string, bool|int> $choices Optional wellness, breakfast, romance booleans and frizzante, canvas_bag quantities.
   * @return array{arrival: string, departure: string, nights: int, items: list<array{code: string, quantity: int, unit_price_cents: int, total_cents: int, unit: string}>, accommodation_cents: int, extras_cents: int, total_cents: int}
   */
  public function calculate(string $arrival, string $departure, array $prices, array $choices = []): array
  {
    $start = DateTimeImmutable::createFromFormat('!Y-m-d', $arrival);
    $end = DateTimeImmutable::createFromFormat('!Y-m-d', $departure);
    if ($start === false || $end === false
      || $start->format('Y-m-d') !== $arrival || $end->format('Y-m-d') !== $departure) {
      throw new InvalidArgumentException('Neplatný dátum príchodu alebo odchodu.');
    }

    $nights = (int) $start->diff($end)->format('%r%a');
    if ($prices['minimum_nights'] < 1 || $prices['minimum_nights'] > 365) {
      throw new InvalidArgumentException('Neplatný minimálny počet nocí.');
    }
    if ($nights < $prices['minimum_nights'] || $nights > 365) {
      throw new InvalidArgumentException('Neplatná dĺžka pobytu.');
    }

    foreach (['first_night', 'additional_night', 'wellness', 'breakfast', 'romance'] as $key) {
      if ($prices[$key] < 0 || $prices[$key] > 10_000_000) {
        throw new InvalidArgumentException('Neplatná cena: ' . $key);
      }
    }

    $boolChoices = ['wellness', 'breakfast', 'romance'];
    $quantityChoices = ['frizzante', 'canvas_bag'];
    foreach ($choices as $code => $value) {
      if (in_array($code, $boolChoices, true)) {
        if (!is_bool($value)) {
          throw new InvalidArgumentException('Neplatný výber služby: ' . $code);
        }
      } elseif (in_array($code, $quantityChoices, true)) {
        if (!is_int($value) || $value < 0 || $value > 20) {
          throw new InvalidArgumentException('Neplatné množstvo: ' . $code);
        }
      } else {
        throw new InvalidArgumentException('Neznáma položka: ' . $code);
      }
    }

    $items = [];
    $firstNightPrice = $prices['first_night'];
    $items[] = self::line('first_night', 1, $firstNightPrice, 'night');
    if ($nights > 1) {
      $items[] = self::line('additional_night', $nights - 1, $prices['additional_night'], 'night');
    }
    $accommodation = $firstNightPrice + ($nights - 1) * $prices['additional_night'];
    $extras = 0;

    foreach (['wellness', 'breakfast'] as $code) {
      if (($choices[$code] ?? false) === true) {
        $line = self::line($code, $nights, $prices[$code], 'night');
        $items[] = $line;
        $extras += $line['total_cents'];
      }
    }
    if (($choices['romance'] ?? false) === true) {
      $line = self::line('romance', 1, $prices['romance'], 'stay');
      $items[] = $line;
      $extras += $line['total_cents'];
    }
    
    foreach (self::PRODUCT_PRICES as $code => $price) {
      $quantity = $choices[$code] ?? 0;
      if ($quantity > 0) {
        $line = self::line($code, $quantity, $price, 'item');
        $items[] = $line;
        $extras += $line['total_cents'];
      }
    }

    return [
      'arrival' => $arrival,
      'departure' => $departure,
      'nights' => $nights,
      'items' => $items,
      'accommodation_cents' => $accommodation,
      'extras_cents' => $extras,
      'total_cents' => $accommodation + $extras,
    ];
  }

  /**
   * Produce a consistent, auditable line item.
   *
   * @param string $code Stable product/service code.
   * @param int $quantity Count of chargeable units.
   * @param int $unitPrice Price per unit in cents.
   * @param string $unit One of night, stay or item.
   * @return array{code: string, quantity: int, unit_price_cents: int, total_cents: int, unit: string}
   */
  private static function line(string $code, int $quantity, int $unitPrice, string $unit): array
  {
    return [
      'code' => $code,
      'quantity' => $quantity,
      'unit_price_cents' => $unitPrice,
      'total_cents' => $quantity * $unitPrice,
      'unit' => $unit,
    ];
  }
}
