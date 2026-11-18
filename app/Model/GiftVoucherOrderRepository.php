<?php

declare(strict_types=1);

namespace App\Model;

use App\Infrastructure\Database\ConnectionFactory;
use DomainException;
use Nette\Database\Explorer;

/**
 * Stores customer voucher requests without issuing an active voucher code.
 */
final class GiftVoucherOrderRepository
{
  private readonly Explorer $database;

  public function __construct(ConnectionFactory $connections)
  {
    $this->database = $connections->createExplorer();
  }

  /**
   * Creates a pending order with a snapshot of the active campaign.
   *
   * @param array<string, mixed> $values
   * @return string Public, nonsequential order reference.
   */
  public function request(int $campaignId, array $values): string
  {
    $name = trim((string) ($values['customer_name'] ?? ''));
    $email = trim((string) ($values['customer_email'] ?? ''));

    if ($name === '' || mb_strlen($name) > 200 || mb_strlen($email) > 254
      || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
      throw new DomainException('Vyplňte platné kontaktné údaje.');
    }

    $connection = $this->database->getConnection();
    $connection->beginTransaction();

    try {
      // Lock the campaign while checking availability and taking a price snapshot.
      $result = $this->database->query(
        'SELECT id FROM voucher_campaigns WHERE id = ? AND is_active = TRUE FOR SHARE',
        $campaignId,
      )->fetch();

      if (!$result) {
        throw new DomainException('Tento darčekový poukaz už nie je dostupný.');
      }

      $campaign = $this->database->table('voucher_campaigns')->get($campaignId);

      if ($campaign === null) {
        throw new DomainException('Tento darčekový poukaz už nie je dostupný.');
      }

      $document = [
        'campaign_name' => (string) $campaign['name'],
        'campaign_name_en' => (string) ($campaign['name_en'] ?? ''),
        'price_cents' => (int) $campaign['price_cents'],
        'nights' => (int) $campaign['nights'],
        'validity_months' => (int) $campaign['validity_months'],
        'includes_wellness' => self::isTrue($campaign['includes_wellness']),
        'includes_wine' => self::isTrue($campaign['includes_wine']),
      ];

      $reference = strtoupper(bin2hex(random_bytes(12)));
      $order = $this->database->table('gift_voucher_orders')->insert([
        'campaign_id' => $campaignId,
        'reference' => $reference,
        'customer_name' => $name,
        'customer_email' => $email,
        'locale' => ($values['locale'] ?? 'sk') === 'en' ? 'en' : 'sk',
        'document' => json_encode($document, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        'price_cents' => $document['price_cents'],
        'status' => 'pending',
      ]);
      $this->database->table('gift_voucher_mail_outbox')->insert([
        'order_id' => (int) $order['id'],
        'event_type' => 'ordered',
      ]);

      $connection->commit();

      return $reference;
    } catch (\Throwable $error) {
      $connection->rollBack();

      throw $error;
    }
  }

  /**
   * Retrieve a minimal public confirmation without customer data.
   *
   * @return array{reference: string, status: string}|null
   */
  public function publicConfirmation(string $reference): ?array
  {
    if (preg_match('/^[A-F0-9]{24}$/D', $reference) !== 1) {
      return null;
    }

    $order = $this->database->table('gift_voucher_orders')->where('reference', $reference)->fetch();

    return $order === null ? null : [
      'reference' => (string) $order['reference'],
      'status' => (string) $order['status'],
    ];
  }

  /**
   * List orders belonging to one campaign for authenticated administration.
   *
   * @return list<array<string, mixed>>
   */
  public function forCampaign(int $campaignId): array
  {
    $orders = [];

    foreach ($this->database->table('gift_voucher_orders')
      ->where('campaign_id', $campaignId)
      ->order('id DESC') as $row) {
      $orders[] = $row->toArray();
    }

    return $orders;
  }

  /**
   * A numeric bank-transfer reference dedicated to voucher orders.
   */
  public static function variableSymbol(int $id): string
  {
    if ($id < 1 || $id > 999999999) {
      throw new DomainException('Číslo objednávky je mimo podporovaného rozsahu.');
    }

    return '6' . str_pad((string) $id, 9, '0', STR_PAD_LEFT);
  }

  /**
   * Mark payment exactly once and issue a voucher using the saved order snapshot.
   * The transaction protects against concurrent administrative submissions.
   */
  public function confirmPayment(int $orderId): void
  {
    $connection = $this->database->getConnection();
    $connection->beginTransaction();

    try {
      $order = $this->database->query(
        'SELECT id, campaign_id, status, document, voucher_id FROM gift_voucher_orders WHERE id = ? FOR UPDATE',
        $orderId,
      )->fetch();

      if ($order === null) {
        throw new DomainException('Objednávka neexistuje.');
      }

      if ($order['status'] === 'paid') {
        $connection->commit();

        return;
      }

      if ($order['status'] !== 'pending') {
        throw new DomainException('Túto objednávku nie je možné označiť ako zaplatenú.');
      }

      $snapshot = $order['document'];
      $snapshot = is_string($snapshot) ? json_decode($snapshot, true, 512, JSON_THROW_ON_ERROR) : $snapshot;

      if (!is_array($snapshot)) {
        throw new DomainException('Objednávka má neplatné údaje.');
      }

      $now = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Bratislava'));
      $months = (int) ($snapshot['validity_months'] ?? 0);

      if ($months < 1 || $months > 36) {
        throw new DomainException('Neplatná platnosť poukazu.');
      }

      $expiry = $now->add(new \DateInterval('P' . $months . 'M'));
      $internalCode = implode('-', str_split(strtoupper(bin2hex(random_bytes(16))), 8));
      $snapshot['name'] = (string) ($snapshot['campaign_name'] ?? '');
      $snapshot['name_en'] = (string) ($snapshot['campaign_name_en'] ?? '');
      $snapshot['code'] = $internalCode;
      $snapshot['issued_date'] = $now->format('Y-m-d');
      $snapshot['expires_date'] = $expiry->format('Y-m-d');

      // ON CONFLICT DO NOTHING safely retries rare alias collisions within the same transaction.
      $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
      $voucherId = null;

      for ($attempt = 0; $attempt < 12; $attempt++) {
        $alias = '';

        for ($i = 0; $i < 8; $i++) {
          $alias .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        $result = $this->database->query(
          'INSERT INTO gift_vouchers (campaign_id, code, short_code, document, expires_at, status)'
          . ' VALUES (?, ?, ?, CAST(? AS jsonb), ?, ?) ON CONFLICT DO NOTHING RETURNING id',
          (int) $order['campaign_id'],
          $internalCode,
          $alias,
          json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
          $expiry->format('Y-m-d'),
          'active',
        )->fetch();

        if ($result !== null) {
          $voucherId = (int) $result['id'];
          break;
        }
      }

      if ($voucherId === null) {
        throw new DomainException('Nepodarilo sa vytvoriť jedinečný kód poukazu.');
      }

      $this->database->query(
        'UPDATE gift_voucher_orders SET status = ?, paid_at = CURRENT_TIMESTAMP, voucher_id = ? WHERE id = ?',
        'paid',
        $voucherId,
        $orderId,
      );
      $this->database->query(
        'INSERT INTO gift_voucher_mail_outbox (order_id, event_type) VALUES (?, ?) ON CONFLICT DO NOTHING',
        $orderId,
        'paid',
      );
      $connection->commit();
    } catch (\Throwable $error) {
      $connection->rollBack();

      throw $error;
    }
  }

  private static function isTrue(mixed $value): bool
  {
    return in_array($value, [true, 1, '1', 't', 'true'], true);
  }
}
