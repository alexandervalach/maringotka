<?php

declare(strict_types=1);

namespace App\Infrastructure\Cms;

use InvalidArgumentException;
use PDO;

final class MediaRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return list<array<string, mixed>> */
    public function gallery(string $locale): array
    {
        if (!in_array($locale, ['sk', 'en'], true)) {
            throw new InvalidArgumentException('Unsupported locale.');
        }
        $stmt = $this->pdo->prepare(<<<'SQL'
            SELECT g.id AS gallery_id, g.position, m.id AS media_id,
                   m.storage_key, m.mime_type, m.width, m.height,
                   t.alt_text, t.caption
            FROM cms_gallery_items g
            JOIN cms_media m ON m.id = g.media_id
            LEFT JOIN cms_media_translations t ON t.media_id = m.id AND t.locale = :locale
            WHERE g.is_visible = true
            ORDER BY g.position ASC, g.id ASC
            SQL);
        $stmt->execute(['locale' => $locale]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function register(
        string $storageKey,
        string $mimeType,
        int $byteSize,
        int $width,
        int $height,
        string $sha256,
    ): int {
        // These checks are defense in depth; physical file validation belongs to the future upload service.
        if (!preg_match('~^[a-zA-Z0-9][a-zA-Z0-9/_-]*\.(jpg|jpeg|png|webp|avif)$~D', $storageKey)
            || str_contains($storageKey, '..')
            || !in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp', 'image/avif'], true)
            || $byteSize < 1 || $byteSize > 15728640 || $width < 1 || $height < 1
            || !preg_match('/^[a-f0-9]{64}$/D', $sha256)
        ) {
            throw new InvalidArgumentException('Invalid media metadata.');
        }
        $stmt = $this->pdo->prepare(<<<'SQL'
            INSERT INTO cms_media (storage_key, mime_type, byte_size, width, height, sha256)
            VALUES (:storage_key, :mime_type, :byte_size, :width, :height, :sha256)
            RETURNING id
            SQL);
        $stmt->execute([
            'storage_key' => $storageKey,
            'mime_type' => $mimeType,
            'byte_size' => $byteSize,
            'width' => $width,
            'height' => $height,
            'sha256' => $sha256,
        ]);
        return (int) $stmt->fetchColumn();
    }

    public function saveTranslation(int $mediaId, string $locale, string $altText, string $caption = ''): void
    {
        if (!in_array($locale, ['sk', 'en'], true)) {
            throw new InvalidArgumentException('Unsupported locale.');
        }
        if (strlen($altText) > 300 || strlen($caption) > 500) {
            throw new InvalidArgumentException('Media description too long.');
        }
        $stmt = $this->pdo->prepare(<<<'SQL'
            INSERT INTO cms_media_translations (media_id, locale, alt_text, caption)
            VALUES (:media_id, :locale, :alt_text, :caption)
            ON CONFLICT (media_id, locale) DO UPDATE SET
                alt_text = EXCLUDED.alt_text, caption = EXCLUDED.caption
            SQL);
        $stmt->execute([
            'media_id' => $mediaId, 'locale' => $locale,
            'alt_text' => $altText, 'caption' => $caption,
        ]);
    }

    public function addToGallery(int $mediaId, int $position, bool $visible = true): void
    {
        if ($position < 0) {
            throw new InvalidArgumentException('Invalid gallery position.');
        }
        $stmt = $this->pdo->prepare(<<<'SQL'
            INSERT INTO cms_gallery_items (media_id, position, is_visible)
            VALUES (:media_id, :position, :visible)
            ON CONFLICT (media_id) DO UPDATE SET
                position = EXCLUDED.position, is_visible = EXCLUDED.is_visible
            SQL);
        $stmt->bindValue(':media_id', $mediaId, PDO::PARAM_INT);
        $stmt->bindValue(':position', $position, PDO::PARAM_INT);
        $stmt->bindValue(':visible', $visible, PDO::PARAM_BOOL);
        $stmt->execute();
    }
}
