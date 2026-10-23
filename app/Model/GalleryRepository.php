<?php

declare(strict_types=1);

namespace App\Model;

use App\Infrastructure\Database\ConnectionFactory;
use App\Localization\SupportedLocales;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/** 
 * Ordering, visibility and localized alt text for the existing gallery media. 
 */
final class GalleryRepository extends Repository
{
  /** 
   * @param ConnectionFactory $connections Project database service. 
   */
  public function __construct(ConnectionFactory $connections)
  {
    parent::__construct($connections->createExplorer(), 'cms_gallery_items');
  }

  /**
   * Read existing gallery items. Before the explicit import, keep all 11 images visible.
   *
   * @return list<array{id: int, key: string, fallback: string, srcset: string, width: int, height: int, position: int, is_visible: bool, alt_sk: string, alt_en: string}>
   */
  public function all(): array
  {
    $catalog = GalleryCatalog::items();
    $byFallback = [];
    foreach ($catalog as $item) {
      $byFallback['assets/images/media/' . $item['fallback']] = $item;
    }
    $result = [];
    foreach ($this->getTable()->order('position ASC, id ASC') as $row) {
      $media = $this->database->table('cms_media')->get((int) $row['media_id']);
      if ($media === null) {
        continue;
      }
      $key = (string) $media['storage_key'];
      if (!isset($byFallback[$key])) {
        continue;
      }
      $alts = ['sk' => '', 'en' => ''];
      foreach ($this->database->table('cms_media_translations')->where('media_id', (int) $row['media_id']) as $translation) {
        $locale = (string) $translation['locale'];
        if (array_key_exists($locale, $alts)) {
          $alts[$locale] = (string) $translation['alt_text'];
        }
      }
      $result[] = [
        ...$byFallback[$key],
        'id' => (int) $row['id'],
        'position' => (int) $row['position'],
        'is_visible' => self::boolValue($row['is_visible']),
        'alt_sk' => $alts['sk'],
        'alt_en' => $alts['en'],
      ];
    }
    if ($result !== []) {
      return $result;
    }
    foreach ($catalog as $index => $item) {
      $result[] = [...$item, 'id' => 0, 'position' => $index + 1,
        'is_visible' => true, 'alt_sk' => '', 'alt_en' => ''];
    }
    return $result;
  }

  /**
   * @param string $locale Supported language.
   * @return list<array{id: int, key: string, fallback: string, srcset: string, width: int, height: int, position: int, is_visible: bool, alt_sk: string, alt_en: string}>
   */
  public function visible(string $locale): array
  {
    SupportedLocales::assertSupported($locale);
    return array_values(array_filter($this->all(), static fn (array $item): bool => $item['is_visible']));
  }

  /**
   * Import only existing optimized JPEG fallbacks from MANIFEST.json; safe to repeat.
   *
   * @return int Newly inserted gallery rows.
   */
  public function importExisting(): int
  {
    $root = dirname(__DIR__, 2) . '/www/assets/images/media/';
    $created = 0;
    $this->database->beginTransaction();
    try {
      foreach (GalleryCatalog::items() as $index => $item) {
        $file = $root . $item['fallback'];
        $image = is_file($file) ? getimagesize($file) : false;
        $bytes = is_file($file) ? filesize($file) : false;
        $hash = is_file($file) ? hash_file('sha256', $file) : false;
        if ($image === false || $bytes === false || $hash === false
          || $image[2] !== IMAGETYPE_JPEG || $bytes < 1 || $bytes > 15728640) {
          throw new RuntimeException('Invalid or missing gallery image: ' . $item['fallback']);
        }
        $storageKey = 'assets/images/media/' . $item['fallback'];
        $this->database->query(
          'INSERT INTO cms_media (storage_key, mime_type, byte_size, width, height, sha256) '
          . 'VALUES (?, ?, ?, ?, ?, ?) ON CONFLICT (storage_key) DO NOTHING',
          $storageKey, 'image/jpeg', $bytes, (int) $image[0], (int) $image[1], $hash,
        );
        $media = $this->database->table('cms_media')->where('storage_key', $storageKey)->fetch();
        if ($media === null) {
          throw new RuntimeException('Cannot find imported image.');
        }
        $exists = $this->getTable()->where('media_id', (int) $media['id'])->count('*') > 0;
        if (!$exists) {
          $this->getTable()->insert(['media_id' => (int) $media['id'], 'position' => $index + 1, 'is_visible' => true]);
          $created++;
        }
      }
      $this->database->commit();
    } catch (Throwable $exception) {
      $this->database->rollBack();
      throw $exception;
    }
    return $created;
  }

  /**
   * Store a complete permutation of the existing gallery item IDs.
   *
   * @param list<int> $ids Ordered gallery item identifiers.
   * @throws InvalidArgumentException When IDs are duplicated, missing or invalid.
   */
  public function reorder(array $ids): void
  {
    if ($ids === [] || count($ids) > 500 || count($ids) !== count(array_unique($ids))) {
      throw new InvalidArgumentException('Invalid gallery order.');
    }

    foreach ($ids as $id) {
      if ($id < 1) {
        throw new InvalidArgumentException('Invalid gallery item ID.');
      }
    }

    $this->database->beginTransaction();

    try {
      $rows = $this->database->query('SELECT id FROM cms_gallery_items ORDER BY id FOR UPDATE')->fetchAll();
      $existing = array_map(static fn ($row): int => (int) $row->id, $rows);
      $sorted = $ids;
      sort($sorted, SORT_NUMERIC);

      if ($sorted !== $existing) {
        throw new InvalidArgumentException('Gallery changed; reload before saving the order.');
      }

      foreach ($ids as $position => $id) {
        $this->database->query('UPDATE cms_gallery_items SET position = ? WHERE id = ?', $position + 1, $id);
      }

      $this->database->commit();
    } catch (Throwable $exception) {
      $this->database->rollBack();
      throw $exception;
    }
  }

  /**
   * @param int $id Existing gallery record.
   * @param int $position Positive display order.
   * @param bool $visible Whether to show the photo.
   * @param string $altSk Slovak alternative text.
   * @param string $altEn English alternative text.
   */
  public function save(int $id, int $position, bool $visible, string $altSk, string $altEn): void
  {
    if ($id < 1 || $position < 1 || $position > 10000 || mb_strlen($altSk) > 300 || mb_strlen($altEn) > 300) {
      throw new InvalidArgumentException('Invalid gallery values.');
    }
    $this->database->beginTransaction();
    try {
      $row = $this->getTable()->get($id);
      if ($row === null) {
        throw new InvalidArgumentException('Gallery photo not found.');
      }
      $row->update(['position' => $position, 'is_visible' => $visible]);
      foreach (['sk' => trim($altSk), 'en' => trim($altEn)] as $locale => $alt) {
        $this->database->query(
          'INSERT INTO cms_media_translations (media_id, locale, alt_text) VALUES (?, ?, ?) '
          . 'ON CONFLICT (media_id, locale) DO UPDATE SET alt_text = EXCLUDED.alt_text',
          (int) $row['media_id'], $locale, $alt,
        );
      }
      $this->database->commit();
    } catch (Throwable $exception) {
      $this->database->rollBack();
      throw $exception;
    }
  }

  /** @param mixed $value PostgreSQL boolean representation. */
  private static function boolValue(mixed $value): bool
  {
    return in_array($value, [true, 1, '1', 't', 'true'], true);
  }
}
