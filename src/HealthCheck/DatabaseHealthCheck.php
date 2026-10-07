<?php

namespace Wexample\SymfonyApi\HealthCheck;

use Doctrine\DBAL\Connection;
use Wexample\SymfonyApi\Interface\HealthCheckInterface;

/**
 * The default connection answers a query.
 */
class DatabaseHealthCheck implements HealthCheckInterface
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function getName(): string
    {
        return 'database';
    }

    public function check(): void
    {
        $this->connection->executeQuery(
            $this->connection->getDatabasePlatform()->getDummySelectSQL()
        );
    }
}
