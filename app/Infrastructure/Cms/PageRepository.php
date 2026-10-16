<?php

declare(strict_types=1);

namespace App\Infrastructure\Cms;

use InvalidArgumentException;
use PDO;

final class PageRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return array<string, mixed>|null */
    public function findPublished(string $slug, string $locale): ?array
    {
        self::assertLocale($locale);
        $stmt = $this->pdo->prepare(<<<'SQL'
            SELECT p.id, p.slug, t.locale, t.title, t.summary, t.body
            FROM cms_pages p
            JOIN cms_page_translations t ON t.page_id = p.id
            WHERE p.slug = :slug AND t.locale = :locale AND p.is_published = true
            SQL);
        $stmt->execute(['slug' => $slug, 'locale' => $locale]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        return $record === false ? null : $record;
    }

    public function create(string $slug): int
    {
        if (!preg_match('/^[a-z][a-z0-9_-]*$/D', $slug) || strlen($slug) > 80) {
            throw new InvalidArgumentException('Invalid page slug.');
        }
        $stmt = $this->pdo->prepare('INSERT INTO cms_pages (slug) VALUES (:slug) RETURNING id');
        $stmt->execute(['slug' => $slug]);
        return (int) $stmt->fetchColumn();
    }

    public function setPublished(int $pageId, bool $published): void
    {
        $stmt = $this->pdo->prepare('UPDATE cms_pages SET is_published = :published, updated_at = now() WHERE id = :id');
        $stmt->bindValue(':published', $published, PDO::PARAM_BOOL);
        $stmt->bindValue(':id', $pageId, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function saveTranslation(int $pageId, string $locale, string $title, string $summary, string $body): void
    {
        self::assertLocale($locale);
        if (trim($title) === '' || strlen($title) > 200) {
            throw new InvalidArgumentException('Invalid page title.');
        }
        $stmt = $this->pdo->prepare(<<<'SQL'
            INSERT INTO cms_page_translations (page_id, locale, title, summary, body)
            VALUES (:page_id, :locale, :title, :summary, :body)
            ON CONFLICT (page_id, locale) DO UPDATE SET
                title = EXCLUDED.title, summary = EXCLUDED.summary,
                body = EXCLUDED.body, updated_at = now()
            SQL);
        $stmt->execute(compact('locale', 'title', 'summary', 'body') + ['page_id' => $pageId]);
    }

    private static function assertLocale(string $locale): void
    {
        if (!in_array($locale, ['sk', 'en'], true)) {
            throw new InvalidArgumentException('Unsupported locale.');
        }
    }
}
