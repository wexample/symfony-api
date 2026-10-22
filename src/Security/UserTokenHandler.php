<?php

namespace Wexample\SymfonyApi\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Wexample\SymfonyApi\DependencyInjection\WexampleSymfonyApiExtension;
use Wexample\SymfonyApi\Enum\UserTokenSecurityEventType;
use Wexample\SymfonyApi\Service\UserTokenService;

/**
 * The `token_handler` of a user token firewall: the request is authenticated
 * as the person holding the token, with their roles. A disabled account is
 * the firewall's `user_checker` to refuse, as for a password login.
 */
class UserTokenHandler extends AbstractApiTokenHandler
{
    public function __construct(
        UserTokenService $userTokenService,
        RequestStack $requestStack,
        #[Autowire(param: 'api_user_token_prefix')]
        string $prefix,
        #[Autowire(param: 'api_user_token_last_used_interval')]
        int $lastUsedInterval,
        #[Autowire(param: 'api_user_token_rate_limit_enabled')]
        bool $rateLimitEnabled,
        #[Autowire(service: 'limiter.' . WexampleSymfonyApiExtension::LIMITER_USER_CLIENT)]
        RateLimiterFactoryInterface $clientLimiter,
        #[Autowire(service: 'limiter.' . WexampleSymfonyApiExtension::LIMITER_USER_IP_FAILURES)]
        RateLimiterFactoryInterface $ipFailuresLimiter,
    ) {
        parent::__construct(
            $userTokenService,
            $requestStack,
            $prefix,
            $lastUsedInterval,
            $rateLimitEnabled,
            $clientLimiter,
            $ipFailuresLimiter,
        );
    }

    public function getEventTypeClass(): string
    {
        return UserTokenSecurityEventType::class;
    }
}
