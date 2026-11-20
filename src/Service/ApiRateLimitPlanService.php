<?php

namespace Wexample\SymfonyApi\Service;

use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * The limiters of `rate_limit_plans`, by the plan's name: a token's holder
 * limited by its plan rather than by its kind's limit.
 */
class ApiRateLimitPlanService
{
    /**
     * @param ContainerInterface $limiters the plans' RateLimiterFactoryInterface, by name
     */
    public function __construct(
        private readonly ContainerInterface $limiters,
    ) {
    }

    public function hasPlan(string $plan): bool
    {
        return $this->limiters->has($plan);
    }

    /**
     * @throws InvalidArgumentException for a plan `rate_limit_plans` does not declare
     */
    public function create(string $plan, string $key): LimiterInterface
    {
        if (! $this->hasPlan($plan)) {
            throw new InvalidArgumentException(sprintf('No api rate limit plan is named "%s".', $plan));
        }

        /** @var RateLimiterFactoryInterface $factory */
        $factory = $this->limiters->get($plan);

        return $factory->create($key);
    }
}
