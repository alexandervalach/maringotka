<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use App\Infrastructure\Admin\AdminCredentials;
use Tester\Assert;

$password = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
$hash = AdminCredentials::hashPassword($password);
Assert::true(str_starts_with($hash, '$argon2id$'));
Assert::true(password_verify($password, $hash));
Assert::false(password_verify('incorrect', $hash));
Assert::exception(static fn () => AdminCredentials::hashPassword('short'), RuntimeException::class);
