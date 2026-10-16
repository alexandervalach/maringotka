<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use App\Infrastructure\Cms\PageRepository;
use App\Infrastructure\Cms\PricingRepository;
use App\Infrastructure\Cms\PublicSettingsRepository;
use Tester\Assert;

// Validation paths must reject invalid input before accessing the uninitialized PDO.
$pdo = (new ReflectionClass(PDO::class))->newInstanceWithoutConstructor();
Assert::exception(fn () => (new PageRepository($pdo))->create('../illegal'), InvalidArgumentException::class);
Assert::exception(fn () => (new PageRepository($pdo))->findPublished('home', 'xx'), InvalidArgumentException::class);
Assert::exception(fn () => (new PublicSettingsRepository($pdo))->set('SECRET-KEY', 'x'), InvalidArgumentException::class);
Assert::exception(fn () => (new PricingRepository($pdo))->setBasePrices(12000, null, 1), InvalidArgumentException::class);
Assert::exception(fn () => (new PricingRepository($pdo))->addSeason('Bad', '2026-02-30', '2026-03-01', 100, 100), InvalidArgumentException::class);
