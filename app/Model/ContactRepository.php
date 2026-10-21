<?php

declare(strict_types=1);

namespace App\Model;

use App\Infrastructure\Database\ConnectionFactory;
use InvalidArgumentException;
use Throwable;

/** Public, non-secret contact details shared by all site languages. */
final class ContactRepository extends Repository
{
  /** @var array<string, string> */
  public const DEFAULTS = [
    'phone' => '+421 951 939 258',
    'email' => 'maringotkanazavadke@gmail.com',
    'instagram_url' => 'https://www.instagram.com/maringotkanazavadke/',
    'booking_url' => 'https://www.booking.com/Share-KWdYAP',
    'maps_url' => 'https://share.google/N34OKl6JS5X3Kh5OC',
    'youtube_url' => 'https://www.youtube.com/watch?v=ItJpIh_QNIM',
  ];

  /** @var array<string, string> */
  private const SETTING_KEYS = [
    'phone' => 'contact_phone',
    'email' => 'contact_email',
    'instagram_url' => 'contact_instagram_url',
    'booking_url' => 'contact_booking_url',
    'maps_url' => 'contact_maps_url',
    'youtube_url' => 'contact_youtube_url',
  ];

  /** @param ConnectionFactory $connections Existing Explorer configuration. */
  public function __construct(ConnectionFactory $connections)
  {
    parent::__construct($connections->createExplorer(), 'public_settings');
  }

  /**
   * Read edited values in one query; fall back to existing public contact details.
   * An explicitly saved empty optional link remains empty.
   *
   * @return array<string, string> Contact field names mapped to their values.
   */
  public function getDetails(): array
  {
    $details = self::DEFAULTS;
    $fieldsByKey = array_flip(self::SETTING_KEYS);
    foreach ($this->getTable()->where('key', array_values(self::SETTING_KEYS)) as $row) {
      $key = (string) $row['key'];
      if (isset($fieldsByKey[$key])) {
        $field = $fieldsByKey[$key];
        $details[$field] = (string) $row['value'];
      }
    }

    // Values may also be modified directly in SQL. Never expose unsafe URL schemes.
    foreach (['instagram_url', 'booking_url', 'maps_url', 'youtube_url'] as $field) {
      if ($details[$field] !== '' && !self::isHttpsUrl($details[$field])) {
        $details[$field] = '';
      }
    }
    if (filter_var($details['email'], FILTER_VALIDATE_EMAIL) === false) {
      $details['email'] = '';
    }

    return $details;
  }

  /**
   * Atomically upsert the six allowed public settings (never credentials).
   *
   * @param array<string, string> $details Values keyed as in DEFAULTS.
   * @return void
   * @throws InvalidArgumentException For missing or invalid data.
   */
  public function save(array $details): void
  {
    $normalized = self::validateAndNormalize($details);
    $this->database->beginTransaction();
    try {
      foreach (self::SETTING_KEYS as $field => $key) {
        $this->database->query(
          'INSERT INTO public_settings (key, value) VALUES (?, ?) '
          . 'ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value, updated_at = now()',
          $key,
          $normalized[$field],
        );
      }
      $this->database->commit();
    } catch (Throwable $exception) {
      $this->database->rollBack();
      throw $exception;
    }
  }

  /**
   * Validate contact values independently of the form, including direct repository calls.
   *
   * @param array<string, string> $details Candidate contact values.
   * @return array<string, string> Validated, trimmed values.
   * @throws InvalidArgumentException On invalid phone, email, or external URL.
   */
  public static function validateAndNormalize(array $details): array
  {
    $normalized = [];
    foreach (self::DEFAULTS as $field => $_) {
      if (!isset($details[$field])) {
        throw new InvalidArgumentException('Missing contact field: ' . $field);
      }
      $normalized[$field] = trim($details[$field]);
    }

    if (strlen($normalized['phone']) > 40
      || preg_match('/^\+?[0-9][0-9\s().-]{5,39}$/D', $normalized['phone']) !== 1) {
      throw new InvalidArgumentException('Invalid contact telephone number.');
    }
    if (strlen($normalized['email']) > 254
      || filter_var($normalized['email'], FILTER_VALIDATE_EMAIL) === false) {
      throw new InvalidArgumentException('Invalid contact email address.');
    }
    foreach (['instagram_url', 'booking_url', 'maps_url', 'youtube_url'] as $field) {
      if (strlen($normalized[$field]) > 2048
        || ($normalized[$field] !== '' && !self::isHttpsUrl($normalized[$field]))) {
        throw new InvalidArgumentException('Invalid HTTPS URL: ' . $field);
      }
    }

    return $normalized;
  }

  /** @param string $value Candidate external link. */
  private static function isHttpsUrl(string $value): bool
  {
    return filter_var($value, FILTER_VALIDATE_URL) !== false
      && strtolower((string) parse_url($value, PHP_URL_SCHEME)) === 'https';
  }
}
