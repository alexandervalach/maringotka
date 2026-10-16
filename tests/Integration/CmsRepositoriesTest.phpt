<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use App\Infrastructure\Database\ConnectionFactory;
use App\Infrastructure\Cms\CmsRepositories;
use Tester\Assert;
use Tester\Environment;

// Opt-in: CI currently has no migration step. Run after Commit 06 migration with CMS_DB_TESTS=1.
if (getenv('CMS_DB_TESTS') !== '1') {
    Environment::skip('Set CMS_DB_TESTS=1 to run PostgreSQL CMS integration tests after migrations.');
}

$pdo = (new ConnectionFactory())->create();
$pdo->beginTransaction();
try {
    $repos = new CmsRepositories($pdo);
    $slug = 'test_' . bin2hex(random_bytes(8));
    $pageId = $repos->pages()->create($slug);
    $repos->pages()->saveTranslation($pageId, 'sk', 'Testovací titulok', 'Perex', 'Telo');
    Assert::null($repos->pages()->findPublished($slug, 'sk'));
    $repos->pages()->setPublished($pageId, true);
    Assert::same('Testovací titulok', $repos->pages()->findPublished($slug, 'sk')['title']);
    Assert::null($repos->pages()->findPublished($slug, 'en'));

    $repos->settings()->set('cms_test_key', 'visible');
    Assert::same('visible', $repos->settings()->get('cms_test_key'));

    $repos->pricing()->setBasePrices(12000, 11000, 1);
    Assert::same(12000, (int) $repos->pricing()->settings()['first_night_price_cents']);
    Assert::same(11000, (int) $repos->pricing()->settings()['additional_night_price_cents']);

    $key = 'test/' . bin2hex(random_bytes(8)) . '.webp';
    $mediaId = $repos->media()->register($key, 'image/webp', 128, 200, 150, str_repeat('a', 64));
    $repos->media()->saveTranslation($mediaId, 'sk', 'Fotografia testu');
    $repos->media()->addToGallery($mediaId, 99999);
    $matches = array_values(array_filter($repos->media()->gallery('sk'), static fn (array $m): bool => $m['storage_key'] === $key));
    Assert::count(1, $matches);
    Assert::same('Fotografia testu', $matches[0]['alt_text']);
} finally {
    $pdo->rollBack();
}
