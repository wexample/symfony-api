<?php

namespace Wexample\SymfonyApi\Interface;

/**
 * A token's holder that says which of `rate_limit_plans` its calls are
 * limited by — a free plan, a paid one — instead of its kind's
 * `rate_limit.client`.
 */
interface ApiRateLimitPlanHolderInterface
{
    /**
     * One of `rate_limit_plans`, null for its kind's limit.
     */
    public function getApiRateLimitPlan(): ?string;
}
