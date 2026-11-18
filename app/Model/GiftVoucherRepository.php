<?php

declare(strict_types=1);

namespace App\Model;

use App\Infrastructure\Database\ConnectionFactory;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use InvalidArgumentException;
use Nette\Database\Explorer;
use Nette\Database\UniqueConstraintViolationException;

/**
 * Issues and retains immutable administrator-created gift vouchers.
 * Issuance does not change the campaign or create a booking.
 */
final class GiftVoucherRepository
{
  private readonly Explorer $database;

  private const ALIAS_ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

  private const ALIAS_LENGTH = 8;

  public function __construct(ConnectionFactory $connections)
  {
    $this->database = $connections->createExplorer();
  }

  /**
   * @return list<array<string, mixed>>
   */
  public function forCampaign(int $campaignId): array
  {
    $vouchers = [];

    foreach ($this->database->table('gift_vouchers')
      ->where('campaign_id', $campaignId)
      ->order('id DESC') as $row) {
      $voucher = $row->toArray();
      $voucher['short_code'] = $this->ensureShortCode((int) $voucher['id'], $voucher['short_code'] ?? null);
      $vouchers[] = $voucher;
    }

    return $vouchers;
  }

  /**
   * Returns the exact document recorded when the voucher was issued.
   *
   * @return array<string, mixed>|null
   */
  public function find(int $id): ?array
  {
    $row = $id > 0 ? $this->database->table('gift_vouchers')->get($id) : null;

    if ($row === null) {
      return null;
    }

    $record = $row->toArray();
    $record['short_code'] = $this->ensureShortCode((int) $record['id'], $record['short_code'] ?? null);
    $document = $record['document'];

    if (is_string($document)) {
      $document = json_decode($document, true, 512, JSON_THROW_ON_ERROR);
    }

    if (!is_array($document)) {
      throw new DomainException('Uložený poukaz má neplatný formát.');
    }

    $document['short_code'] = $record['short_code'];
    $record['document'] = $document;

    return $record;
  }

  /**
   * Creates one activated voucher with an unpredictable 128-bit code.
   * All editable parameters are snapshotted, so later campaign edits are safe.
   *
   * @param array<string, mixed> $campaign
   * @param array<string, mixed> $values
   * @return int Newly issued voucher identifier.
   */
  public function issue(array $campaign, array $values): int
  {
    $campaignId = (int) ($campaign['id'] ?? 0);

    if ($campaignId < 1) {
      throw new InvalidArgumentException('Neplatná kampaň.');
    }

    $price = trim((string) ($values['price'] ?? ''));

    if (preg_match('/^([0-9]{1,5})(?:[.,]([0-9]{1,2}))?$/D', $price, $match) !== 1) {
      throw new InvalidArgumentException('Neplatná cena poukazu.');
    }

    $priceCents = (int) $match[1] * 100 + (int) str_pad($match[2] ?? '', 2, '0');
    $nights = filter_var($values['nights'] ?? null, FILTER_VALIDATE_INT);
    $months = filter_var($values['validity_months'] ?? null, FILTER_VALIDATE_INT);

    if ($priceCents < 100 || $nights === false || $nights < 1 || $nights > 30
      || $months === false || $months < 1 || $months > 36) {
      throw new InvalidArgumentException('Neplatné parametre poukazu.');
    }

    $issuedAt = new DateTimeImmutable('now', new DateTimeZone('Europe/Bratislava'));
    $expiresAt = $issuedAt->add(new \DateInterval('P' . $months . 'M'));
    $random = strtoupper(bin2hex(random_bytes(16)));
    $code = implode('-', str_split($random, 8));
    $document = [
      'code' => $code,
      'name' => (string) $campaign['name'],
      'name_en' => (string) ($campaign['name_en'] ?? ''),
      'price_cents' => $priceCents,
      'nights' => $nights,
      'validity_months' => $months,
      'includes_wellness' => (bool) ($values['includes_wellness'] ?? false),
      'includes_wine' => (bool) ($values['includes_wine'] ?? false),
      'issued_date' => $issuedAt->format('Y-m-d'),
      'expires_date' => $expiresAt->format('Y-m-d'),
    ];

    // Alias collisions are handled by the database UNIQUE constraint.
    // The 128-bit internal code is retained for future voucher verification.
    for ($attempt = 0; $attempt < 12; $attempt++) {
      try {
        $row = $this->database->table('gift_vouchers')->insert([
          'campaign_id' => $campaignId,
          'code' => $code,
          'short_code' => self::randomShortCode(),
          'document' => json_encode($document, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
          'expires_at' => $expiresAt->format('Y-m-d'),
          'status' => 'active',
        ]);

        return (int) $row['id'];
      } catch (UniqueConstraintViolationException $exception) {
        if ($attempt === 11) {
          throw $exception;
        }
      }
    }

    throw new DomainException('Nepodarilo sa prideliť kód poukazu.');
  }

  /**
   * Assigns a human-friendly alias to vouchers issued before this change.
   * The original identifier and document snapshot stay unchanged.
   */
  private function ensureShortCode(int $id, mixed $existing): string
  {
    if (is_string($existing) && $existing !== '') {
      return $existing;
    }

    for ($attempt = 0; $attempt < 12; $attempt++) {
      try {
        $this->database->query(
          'UPDATE gift_vouchers SET short_code = ? WHERE id = ? AND short_code IS NULL',
          self::randomShortCode(),
          $id,
        );
      } catch (UniqueConstraintViolationException $exception) {
        if ($attempt === 11) {
          throw $exception;
        }

        continue;
      }

      $row = $this->database->table('gift_vouchers')->get($id);

      if ($row !== null && is_string($row['short_code']) && $row['short_code'] !== '') {
        return $row['short_code'];
      }
    }

    throw new DomainException('Nepodarilo sa prideliť krátky kód poukazu.');
  }

  /**
   * Eight characters from a 32-symbol alphabet (~40 random bits).
   * Rate limiting will also be required at the future redemption endpoint.
   */
  private static function randomShortCode(): string
  {
    $code = '';

    for ($i = 0; $i < self::ALIAS_LENGTH; $i++) {
      $code .= self::ALIAS_ALPHABET[random_int(0, strlen(self::ALIAS_ALPHABET) - 1)];
    }

    return $code;
  }
}
