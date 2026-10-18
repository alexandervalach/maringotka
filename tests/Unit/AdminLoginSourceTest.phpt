<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use Tester\Assert;

$root = dirname(__DIR__, 2);
$template = file_get_contents($root . '/app/Presenters/templates/Admin/login.latte');
$migration = file_get_contents($root . '/migrations/202610180001_admin_login_hardening.sql');
$presenter = file_get_contents($root . '/app/Presenters/AdminPresenter.php');
if ($template === false || $migration === false || $presenter === false) {
    throw new RuntimeException('Commit 09 repair source file is missing.');
}
Assert::contains('{form loginForm', $template);
Assert::contains('{input username', $template);
Assert::contains('{input password', $template);
Assert::contains('ADD COLUMN username', $migration);
Assert::contains('admin_login_attempts', $migration);
Assert::contains('$this->session->start();', $presenter);
