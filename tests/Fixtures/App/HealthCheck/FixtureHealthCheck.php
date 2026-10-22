<?php

namespace Wexample\SymfonyApi\Tests\Fixtures\App\HealthCheck;

use RuntimeException;
use Wexample\SymfonyApi\Interface\HealthCheckInterface;

/**
 * A probe contributed by the application, made to fail on demand.
 */
class FixtureHealthCheck implements HealthCheckInterface
{
    public bool $failing = false;

    public function getName(): string
    {
        return 'fixture';
    }

    public function check(): void
    {
        if ($this->failing) {
            throw new RuntimeException('Connection refused to fixture://secret-host:1234.');
        }
    }
}
