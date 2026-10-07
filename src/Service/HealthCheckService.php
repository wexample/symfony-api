<?php

namespace Wexample\SymfonyApi\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Throwable;
use Wexample\SymfonyApi\Interface\HealthCheckInterface;

/**
 * Runs every probe, each on its own: a failing one never stops the others.
 */
class HealthCheckService
{
    public const string STATUS_OK = 'ok';

    public const string STATUS_FAIL = 'fail';

    /**
     * @param iterable<HealthCheckInterface> $checks
     */
    public function __construct(
        #[AutowireIterator(HealthCheckInterface::TAG)]
        private readonly iterable $checks,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{status: string, checks: object} each probe's `status` and `durationMs`, by its name
     */
    public function run(): array
    {
        $status = self::STATUS_OK;
        $results = [];

        foreach ($this->checks as $check) {
            $start = hrtime(true);

            try {
                $check->check();
                $checkStatus = self::STATUS_OK;
            } catch (Throwable $exception) {
                $checkStatus = $status = self::STATUS_FAIL;

                $this->logger->error('Health check failed: {check}.', [
                    'check' => $check->getName(),
                    'exception' => $exception,
                ]);
            }

            $results[$check->getName()] = [
                'status' => $checkStatus,
                'durationMs' => intdiv(hrtime(true) - $start, 1_000_000),
            ];
        }

        return [
            'status' => $status,
            // An object in JSON even with no probe at all.
            'checks' => (object) $results,
        ];
    }
}
