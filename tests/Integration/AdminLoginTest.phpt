<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use App\Infrastructure\Admin\AdminCredentials;
use App\Infrastructure\Admin\AdminLoginLimiter;
use App\Infrastructure\Database\ConnectionFactory;
use Tester\Assert;
use Tester\Environment;

if (getenv('ADMIN_DB_TESTS') !== '1') {
    Environment::skip('Set ADMIN_DB_TESTS=1 after applying the Commit 09 repair migration.');
}

$pdo = (new ConnectionFactory())->create();
$pdo->beginTransaction();
try {
    $account = new AdminCredentials($pdo);
    $password = 'strong-test-password-' . bin2hex(random_bytes(16));
    $account->setPasswordHash(AdminCredentials::hashPassword($password));
    $pdo->exec("UPDATE admin_account SET username = 'testadmin' WHERE singleton_id = 1");

    Assert::true($account->verify('testadmin', $password));
    Assert::false($account->verify('anotheruser', $password));
    Assert::false($account->verify('testadmin', 'incorrect-password'));

    $limiter = new AdminLoginLimiter($pdo);
    $ip = '192.0.2.123';  // Reserved documentation-only address.
    $otherIp = '192.0.2.124';
    $limiter->clear($ip);
    Assert::false($limiter->isBlocked($ip));
    for ($i = 0; $i < 5; ++$i) {
        $limiter->recordFailure($ip);
    }
    Assert::true($limiter->isBlocked($ip));
    Assert::false($limiter->isBlocked($otherIp));
    // A completed 15-minute window should allow another attempt.
    $pdo->exec("UPDATE admin_login_attempts SET first_failed_at = now() - INTERVAL '16 minutes', blocked_until = now() - INTERVAL '1 second' WHERE ip_address = '192.0.2.123'");
    Assert::false($limiter->isBlocked($ip));
    $limiter->recordFailure($ip);
    Assert::false($limiter->isBlocked($ip));
    $limiter->clear($ip);
    Assert::false($limiter->isBlocked($ip));
    Assert::true($limiter->isBlocked('not-an-ip'));
} finally {
    $pdo->rollBack();
}
