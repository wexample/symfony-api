<?php

namespace Wexample\SymfonyApi\Interface;

/**
 * One dependency the application cannot work without — its database, its
 * queue — probed by the health endpoint. Any autoconfigured service
 * implementing it is picked up: a package contributes the probe of what it
 * ships.
 */
interface HealthCheckInterface
{
    public const string TAG = 'wexample_symfony_api.health_check';

    /**
     * The key of this probe in the response: `database`, `queue`.
     */
    public function getName(): string;

    /**
     * Returns when the dependency answers, throws otherwise. The exception is
     * logged, never sent: it may quote a host or a DSN.
     */
    public function check(): void;
}
