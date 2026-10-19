<?php

declare(strict_types=1);

namespace App\Model;

use App\Infrastructure\Database\ConnectionFactory;
use InvalidArgumentException;
use Nette\Database\Table\ActiveRow;
use Throwable;

/** 
 * Persists homepage translations; saving makes the updated text public. 
 */
final class HomepageRepository extends Repository
{
  private const SLUG = 'home';

  /** @param ConnectionFactory $connections Existing PostgreSQL connection configuration. */
  public function __construct(ConnectionFactory $connections)
  {
    parent::__construct($connections->createExplorer(), 'cms_pages');
  }

  /**
   * @param string $locale Language code (sk or en).
   * @return array{title: string, summary: string}|null Published translation, if available.
   */
  public function findPublished(string $locale): ?array
  {
    self::assertLocale($locale);
    $page = $this->page();
    if ($page === null || !self::published($page)) {
      return null;
    }

    $translation = $this->translation((int) $page['id'], $locale);
    if ($translation === null) {
      return null;
    }

    return [
      'title' => (string) $translation['title'],
      'summary' => (string) $translation['summary'],
    ];
  }

  /**
   * @param string $locale Language code (sk or en).
   * @return array{title: ?string, summary: ?string} Saved editor state.
   */
  public function findForEditing(string $locale): array
  {
    self::assertLocale($locale);
    $page = $this->page();
    if ($page === null) {
      return ['title' => null, 'summary' => null];
    }

    $translation = $this->translation((int) $page['id'], $locale);
    return [
      'title' => $translation === null ? null : (string) $translation['title'],
      'summary' => $translation === null ? null : (string) $translation['summary'],
    ];
  }

  /**
   * Save the selected translation and make the homepage publicly visible atomically.
   *
   * @param string $locale Language code (sk or en).
   * @param string $title Nonempty homepage heading (max. 200 characters).
   * @param string $summary Homepage introductory text.
   * @return void
   * @throws InvalidArgumentException For invalid locale or an empty heading.
   */
  public function save(string $locale, string $title, string $summary): void
  {
    self::assertLocale($locale);
    $title = trim($title);
    $titleLength = preg_match_all('/./us', $title);
    if ($title === '' || $titleLength === false || $titleLength > 200) {
      throw new InvalidArgumentException('Homepage title must contain 1–200 characters.');
    }
    $summaryLength = preg_match_all('/./us', $summary);
    if ($summaryLength === false || $summaryLength > 4000) {
      throw new InvalidArgumentException('Homepage summary exceeds 4000 characters.');
    }

    $this->database->beginTransaction();
    try {
      $this->database->query(
        'INSERT INTO cms_pages (slug) VALUES (?) ON CONFLICT (slug) DO NOTHING',
        self::SLUG,
      );
      $page = $this->page();
      if ($page === null) {
        throw new \RuntimeException('Homepage page was not created.');
      }
      $this->database->query(
        'INSERT INTO cms_page_translations (page_id, locale, title, summary, body) '
        . 'VALUES (?, ?, ?, ?, ?) '
        . 'ON CONFLICT (page_id, locale) DO UPDATE SET '
        . 'title = EXCLUDED.title, summary = EXCLUDED.summary, updated_at = now()',
        (int) $page['id'],
        $locale,
        $title,
        $summary,
        '',
      );
      $page->update(['is_published' => true, 'updated_at' => new \DateTimeImmutable()]);
      $this->database->commit();
    } catch (Throwable $exception) {
      $this->database->rollBack();
      throw $exception;
    }
  }

  /** 
   * @return ActiveRow|null Homepage page row, if created. 
   */
  private function page(): ?ActiveRow
  {
    return $this->getTable()->where('slug', self::SLUG)->fetch() ?: null;
  }

  /**
   * @param int $pageId Homepage page ID.
   * @param string $locale Language code.
   * @return ActiveRow|null Existing translation row.
   */
  private function translation(int $pageId, string $locale): ?ActiveRow
  {
    return $this->database->table('cms_page_translations')
      ->where('page_id', $pageId)
      ->where('locale', $locale)
      ->fetch() ?: null;
  }

  /** 
   * @param ActiveRow $page Homepage row. 
   */
  private static function published(ActiveRow $page): bool
  {
    return in_array($page['is_published'], [true, 1, '1', 't', 'true'], true);
  }

  /** 
   * @param string $locale Language code to validate. 
   */
  private static function assertLocale(string $locale): void
  {
    if ($locale !== 'sk' && $locale !== 'en') {
      throw new InvalidArgumentException('Unsupported homepage locale.');
    }
  }
}
