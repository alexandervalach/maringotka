<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Infrastructure\Monitoring\ErrorStore;
use Tester\Assert;

$directory = sys_get_temp_dir() . '/maringotka-monitor-' . bin2hex(random_bytes(8));
$store = new ErrorStore($directory);

$createError = static function (): RuntimeException {
    return new RuntimeException('Private details must never be stored in notification records');
};

try {
    $first = $store->record($createError(), 'test-1');
    $second = $store->record($createError(), 'test-1');
    Assert::same($first['fingerprint'], $second['fingerprint']);
    Assert::same(1, $first['count']);
    Assert::same(2, $second['count']);
    Assert::true($first['queued']);
    Assert::false($second['queued']);
    Assert::count(1, $store->pending());
    $issue = $store->read($directory . '/issue-' . $first['fingerprint'] . '.json');
    Assert::same(2, $issue['count']);
    Assert::false(str_contains((string) file_get_contents($directory . '/issue-' . $first['fingerprint'] . '.json'), 'Private details'));
} finally {
    foreach (glob($directory . '/*') ?: [] as $path) {
        unlink($path);
    }
    foreach (glob($directory . '/.*') ?: [] as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }
    rmdir($directory);
}
