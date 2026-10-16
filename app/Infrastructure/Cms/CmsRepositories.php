<?php

declare(strict_types=1);

namespace App\Infrastructure\Cms;

use PDO;

/** A common PDO is exposed for transactions that span multiple repositories. */
final class CmsRepositories
{
    public function __construct(public readonly PDO $pdo)
    {
    }

    public function pages(): PageRepository { return new PageRepository($this->pdo); }
    public function media(): MediaRepository { return new MediaRepository($this->pdo); }
    public function pricing(): PricingRepository { return new PricingRepository($this->pdo); }
    public function settings(): PublicSettingsRepository { return new PublicSettingsRepository($this->pdo); }
}
