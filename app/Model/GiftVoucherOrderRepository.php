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
      $this->database->table('gift_voucher_orders')->insert([
        'campaign_id' => $campaignId,
        'reference' => $reference,
        'customer_name' => $name,
        'customer_email' => $email,
        'locale' => ($values['locale'] ?? 'sk') === 'en' ? 'en' : 'sk',
        'document' => json_encode($document, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        'price_cents' => $document['price_cents'],
        'status' => 'pending',
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

  private static function isTrue(mixed $value): bool
  {
    return in_array($value, [true, 1, '1', 't', 'true'], true);
  }
}
