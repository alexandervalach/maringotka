<?php

declare(strict_types=1);

namespace App\Infrastructure\Cms;

use App\Infrastructure\Database\ConnectionFactory;

/** Provides repositories sharing one PDO connection for a single operation/request. */
final class CmsRepositoryFactory
{
    public function __construct(private ConnectionFactory $connectionFactory)
    {
    }

    public function connect(): CmsRepositories
    {
        return new CmsRepositories($this->connectionFactory->create());
    }
}

