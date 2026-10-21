<?php

declare(strict_types=1);

namespace App\Model;

use App\Infrastructure\Database\ConnectionFactory;
use InvalidArgumentException;
use Throwable;

/** 
 * Rates for one accommodation, shared by all language versions. 
 */
final class PricingRepository extends Repository
{
  /** @var array<string, int> Amounts in eurocents. */
  private const DEFAULTS = [
    'first_night' => 12000,
    'additional_night' => 11000,
    'wellness' => 2000,
    'breakfast' => 2000,
    'romance' => 4000,
  ];

  /** @var array<string, array{code: string, unit: string}> */
  private const EXTRAS = [
    'wellness' => ['code' => 'wellness', 'unit' => 'per_day'],
    'breakfast' => ['code' => 'breakfast', 'unit' => 'per_day'],
    'romance' => ['code' => 'romance', 'unit' => 'per_stay'],
  ];

  /** 
   * @param ConnectionFactory $connections Existing Nette database configuration. 
   */
  public function __construct(ConnectionFactory $connections)
  {
    parent::__construct($connections->createExplorer(), 'pricing_settings');
  }

  /**
   * Read saved pricing and use current public prices until edited.
   *
   * @return array{first_night: int, additional_night: int, wellness: int, breakfast: int, romance: int, minimum_nights: int}
   */
  public function getPrices(): array
  {
    $prices = [...self::DEFAULTS, 'minimum_nights' => 1];
    $settings = $this->getTable()->get(1);
    if ($settings !== null) {
      if ($settings['first_night_price_cents'] !== null) {
        $prices['first_night'] = (int) $settings['first_night_price_cents'];
      }
      if ($settings['additional_night_price_cents'] !== null) {
        $prices['additional_night'] = (int) $settings['additional_night_price_cents'];
      }
      $prices['minimum_nights'] = (int) $settings['minimum_nights'];
    }

    foreach ($this->database->table('pricing_extras')->where('code', array_keys(self::EXTRAS)) as $extra) {
      $code = (string) $extra['code'];
      if (isset($prices[$code]) && self::isTrue($extra['is_active'])) {
        $prices[$code] = (int) $extra['price_cents'];
      }
    }
    return $prices;
  }

  /**
   * Pre-fill the editor with localized decimal notation.
   *
   * @return array<string, string|int> Form field values.
   */
  public function getEditorValues(): array
  {
    $prices = $this->getPrices();
    $values = ['minimum_nights' => $prices['minimum_nights']];
    foreach (self::DEFAULTS as $field => $_) {
      $values[$field] = self::formatAmount($prices[$field]);
    }
    return $values;
  }

  /**
   * Format prices for the public SK/EN templates without changing their design.
   *
   * @return array<string, string> Formatted euro amounts (without the € sign).
   */
  public function getDisplayPrices(): array
  {
    $prices = $this->getPrices();
    $formatted = [];
    foreach (self::DEFAULTS as $field => $_) {
      $formatted[$field] = self::formatAmount($prices[$field]);
    }
    return $formatted;
  }

  /**
   * Atomically update base rates and three currently offered extras.
   * Seasonal overrides and unavailable bike rental are intentionally untouched.
   *
   * @param array<string, string|int> $values Submitted editor values.
   * @throws InvalidArgumentException For invalid prices or minimum stay.
   */
  public function save(array $values): void
  {
    $amounts = self::normalize($values);
    $minimum = $values['minimum_nights'] ?? null;
    if (!is_int($minimum) || $minimum < 1 || $minimum > 365) {
      throw new InvalidArgumentException('Minimum stay must be between 1 and 365 nights.');
    }

    $this->database->beginTransaction();
    try {
      $this->database->query(
        'INSERT INTO pricing_settings (singleton_id, first_night_price_cents, additional_night_price_cents, minimum_nights) '
        . 'VALUES (1, ?, ?, ?) ON CONFLICT (singleton_id) DO UPDATE SET '
        . 'first_night_price_cents = EXCLUDED.first_night_price_cents, '
        . 'additional_night_price_cents = EXCLUDED.additional_night_price_cents, '
        . 'minimum_nights = EXCLUDED.minimum_nights, updated_at = now()',
        $amounts['first_night'],
        $amounts['additional_night'],
        $minimum,
      );
      foreach (self::EXTRAS as $field => $extra) {
        $this->database->query(
          'INSERT INTO pricing_extras (code, charge_unit, price_cents, is_active) '
          . 'VALUES (?, ?, ?, true) ON CONFLICT (code) DO UPDATE SET '
          . 'charge_unit = EXCLUDED.charge_unit, price_cents = EXCLUDED.price_cents, is_active = true',
          $extra['code'],
          $extra['unit'],
          $amounts[$field],
        );
      }
      $this->database->commit();
    } catch (Throwable $exception) {
      $this->database->rollBack();
      throw $exception;
    }
  }

  /**
   * Parse exact decimal euro amounts; never use floating-point money arithmetic.
   *
   * @param array<string, string|int> $values Editor values.
   * @return array<string, int> Parsed eurocents.
   * @throws InvalidArgumentException For malformed or excessive values.
   */
  public static function normalize(array $values): array
  {
    $amounts = [];
    foreach (self::DEFAULTS as $field => $_) {
      $input = $values[$field] ?? null;
      if (!is_string($input) || preg_match('/^([0-9]{1,5})(?:[.,]([0-9]{1,2}))?$/D', trim($input), $matches) !== 1) {
        throw new InvalidArgumentException('Invalid price: ' . $field);
      }
      $euros = (int) $matches[1];
      $cents = (int) str_pad($matches[2] ?? '', 2, '0');
      $amounts[$field] = $euros * 100 + $cents;
    }
    return $amounts;
  }

  /** @param int $cents Nonnegative amount in eurocents. */
  public static function formatAmount(int $cents): string
  {
    $euros = intdiv($cents, 100);
    $remainder = $cents % 100;
    return $remainder === 0 ? (string) $euros : sprintf('%d,%02d', $euros, $remainder);
  }

  /** 
   * @param mixed $value PostgreSQL boolean representation. 
   */
  private static function isTrue(mixed $value): bool
  {
    return in_array($value, [true, 1, '1', 't', 'true'], true);
  }
}
