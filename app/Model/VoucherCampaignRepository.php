<?php

declare(strict_types=1);

namespace App\Model;

use App\Infrastructure\Database\ConnectionFactory;
use InvalidArgumentException;
use Nette\Database\Explorer;

/**
 * Manages voucher offers and their public availability.
 * Issuing, payment and redemption are deliberately outside this repository.
 */
final class VoucherCampaignRepository
{
  private readonly Explorer $database;

  public function __construct(ConnectionFactory $connections)
  {
    $this->database = $connections->createExplorer();
  }

  /**
   * @return list<array<string, mixed>>
   */
  public function all(): array
  {
    $rows = [];

    foreach ($this->database->table('voucher_campaigns')->order('id DESC') as $campaign) {
      $rows[] = $campaign->toArray();
    }

    return $rows;
  }

  /**
   * @return array<string, mixed>|null
   */
  public function find(int $id): ?array
  {
    if ($id < 1) {
      return null;
    }

    $campaign = $this->database->table('voucher_campaigns')->get($id);

    return $campaign?->toArray();
  }

  /**
   * List campaigns currently visible on the public website.
   *
   * @return list<array<string, mixed>>
   */
  public function active(): array
  {
    $rows = [];

    foreach ($this->database->table('voucher_campaigns')
      ->where('is_active', true)
      ->order('id DESC') as $campaign) {
      $rows[] = $campaign->toArray();
    }

    return $rows;
  }

  /**
   * Persist a campaign. Updating never creates a new campaign automatically.
   *
   * @param array<string, mixed> $values
   */
  public function save(?int $id, array $values): int
  {
    $data = self::normalize($values);

    if ($id === null) {
      $campaign = $this->database->table('voucher_campaigns')->insert($data);
      return (int) $campaign['id'];
    }

    $campaign = $this->database->table('voucher_campaigns')->get($id);
    if ($campaign === null) {
      throw new InvalidArgumentException('Kampaň neexistuje.');
    }

    $campaign->update($data);

    return $id;
  }

  /**
   * Toggle only the selected campaign.
   */
  public function setActive(int $id, bool $active): void
  {
    if ($id < 1) {
      throw new InvalidArgumentException('Neplatná kampaň.');
    }

    $count = $this->database->table('voucher_campaigns')
      ->where('id', $id)
      ->update(['is_active' => $active]);

    if ($count !== 1) {
      throw new InvalidArgumentException('Kampaň neexistuje.');
    }
  }

  /**
   * Validate and normalize user-provided campaign data.
   * Money is stored as integer cents, never floating point.
   *
   * @param array<string, mixed> $values
   * @return array{name: string, name_en: string, price_cents: int, nights: int, validity_months: int, includes_wellness: bool, includes_wine: bool, is_active: bool, updated_at: \Nette\Database\SqlLiteral}
   */
  public static function normalize(array $values): array
  {
    $name = trim((string) ($values['name'] ?? ''));
    if ($name === '' || strlen($name) > 120) {
      throw new InvalidArgumentException('Názov kampane musí mať 1 až 120 znakov.');
    }

    $nameEn = trim((string) ($values['name_en'] ?? ''));
    if (strlen($nameEn) > 120) {
      throw new InvalidArgumentException('Anglický názov môže mať najviac 120 znakov.');
    }

    $price = trim((string) ($values['price'] ?? ''));
    if (preg_match('/^([0-9]{1,5})(?:[.,]([0-9]{1,2}))?$/D', $price, $match) !== 1) {
      throw new InvalidArgumentException('Neplatná cena poukazu.');
    }

    $cents = (int) $match[1] * 100 + (int) str_pad($match[2] ?? '', 2, '0');
    if ($cents < 100) {
      throw new InvalidArgumentException('Cena musí byť aspoň 1 €.');
    }

    $nights = filter_var($values['nights'] ?? null, FILTER_VALIDATE_INT);
    if ($nights === false || $nights < 1 || $nights > 30) {
      throw new InvalidArgumentException('Počet nocí musí byť od 1 do 30.');
    }

    $months = filter_var($values['validity_months'] ?? null, FILTER_VALIDATE_INT);
    if ($months === false || $months < 1 || $months > 36) {
      throw new InvalidArgumentException('Platnosť musí byť od 1 do 36 mesiacov.');
    }

    return [
      'name' => $name,
      'name_en' => $nameEn,
      'price_cents' => $cents,
      'nights' => $nights,
      'validity_months' => $months,
      'includes_wellness' => self::isTrue($values['includes_wellness'] ?? false),
      'includes_wine' => self::isTrue($values['includes_wine'] ?? false),
      'is_active' => self::isTrue($values['is_active'] ?? false),
      'updated_at' => new \Nette\Database\SqlLiteral('now()'),
    ];
  }

  /**
   * Format monetary amounts for the administrative form.
   */
  public static function euros(int $cents): string
  {
    return sprintf('%d,%02d', intdiv($cents, 100), $cents % 100);
  }

  /**
   * Interpret PostgreSQL boolean values consistently.
   */
  private static function isTrue(mixed $value): bool
  {
    return in_array($value, [true, 1, '1', 't', 'true'], true);
  }
}
