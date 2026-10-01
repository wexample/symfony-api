<?php

namespace Wexample\SymfonyApi\Security;

use DateTimeImmutable;
use LogicException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Wexample\SymfonyApi\DependencyInjection\WexampleSymfonyApiExtension;
use Wexample\SymfonyApi\Enum\MachineTokenRefusalCause;
use Wexample\SymfonyApi\Exception\MachineTokenRefusedException;
use Wexample\SymfonyApi\Exception\MachineTokenThrottledException;
use Wexample\SymfonyApi\Helper\MachineTokenHelper;
use Wexample\SymfonyApi\Service\MachineTokenService;

/**
 * Resolves the bearer token of a machine firewall to its client, for the
 * native `access_token` authenticator of Symfony.
 *
 * Unknown, revoked and expired tokens fail the same way: the response tells a
 * caller nothing about which tokens exist. The cause travels in the exception,
 * for the journal.
 */
class MachineTokenHandler implements AccessTokenHandlerInterface
{
    /**
     * The token entity once found, for MachineTokenSecuritySubscriber.
     */
    public const string REQUEST_ATTRIBUTE_TOKEN = '_wexample_api_machine_token';

    /**
     * Set as soon as this handler reads a token: marks the request as a
     * machine authentication attempt, and names the token for the journal.
     */
    public const string REQUEST_ATTRIBUTE_HINT = '_wexample_api_machine_token_hint';

    /**
     * @param list<string> $allowedRoles
     */
    public function __construct(
        private readonly MachineTokenService $machineTokenService,
        private readonly RequestStack $requestStack,
        private readonly LoggerInterface $logger,
        #[Autowire(param: 'api_machine_token_class')]
        private readonly ?string $tokenClass,
        #[Autowire(param: 'api_machine_token_prefix')]
        private readonly string $prefix,
        #[Autowire(param: 'api_machine_token_roles')]
        private readonly array $allowedRoles,
        #[Autowire(param: 'api_machine_token_rate_limit_enabled')]
        private readonly bool $rateLimitEnabled,
        #[Autowire(service: 'limiter.' . WexampleSymfonyApiExtension::LIMITER_MACHINE_CLIENT)]
        private readonly RateLimiterFactoryInterface $clientLimiter,
        #[Autowire(service: 'limiter.' . WexampleSymfonyApiExtension::LIMITER_MACHINE_IP_FAILURES)]
        private readonly RateLimiterFactoryInterface $ipFailuresLimiter,
    ) {
    }

    public function getUserBadgeFrom(string $accessToken): UserBadge
    {
        if (null === $this->tokenClass) {
            throw new LogicException('Set wexample_symfony_api.machine_token.token_class to authenticate machine clients.');
        }

        // A string that is not one of ours may be someone else's secret: no
        // part of it goes to the journal.
        $hint = str_starts_with($accessToken, $this->prefix)
            ? MachineTokenHelper::buildHint($accessToken, $this->prefix)
            : null;

        $request = $this->requestStack->getMainRequest();
        $request?->attributes->set(self::REQUEST_ATTRIBUTE_HINT, $hint);

        // An address that failed too often is stopped before the database.
        if ($this->rateLimitEnabled && $request) {
            $limit = $this->ipFailuresLimiter->create($request->getClientIp())->consume(0);

            if (0 === $limit->getRemainingTokens()) {
                throw new MachineTokenThrottledException(MachineTokenThrottledException::LIMIT_IP, $limit->getRetryAfter());
            }
        }

        $token = $this->machineTokenService->findTokenBySecret($accessToken);
        $now = new DateTimeImmutable();

        if (null === $token) {
            throw new MachineTokenRefusedException(MachineTokenRefusalCause::UNKNOWN, $hint);
        }

        $client = $token->getClient();

        if ($token->isRevoked($now)) {
            throw new MachineTokenRefusedException(MachineTokenRefusalCause::REVOKED, $hint, $client);
        }

        if ($token->isExpired($now)) {
            throw new MachineTokenRefusedException(MachineTokenRefusalCause::EXPIRED, $hint, $client);
        }

        $forbiddenRoles = array_diff($client->getRoles(), $this->allowedRoles);

        // A machine client carrying a role meant for people is a configuration
        // mistake: refuse it rather than open the doors that role opens.
        if (! empty($forbiddenRoles)) {
            $this->logger->error('Machine client refused: it carries roles outside wexample_symfony_api.machine_token.roles.', [
                'token' => $hint,
                'roles' => array_values($forbiddenRoles),
            ]);

            throw new MachineTokenRefusedException(MachineTokenRefusalCause::ROLE_NOT_ALLOWED, $hint, $client);
        }

        if ($this->rateLimitEnabled) {
            $limit = $this->clientLimiter->create($token->getClient()->getUserIdentifier())->consume();

            if (! $limit->isAccepted()) {
                throw new MachineTokenThrottledException(MachineTokenThrottledException::LIMIT_CLIENT, $limit->getRetryAfter(), $client);
            }
        }

        $request?->attributes->set(self::REQUEST_ATTRIBUTE_TOKEN, $token);

        return new UserBadge(
            $client->getUserIdentifier(),
            fn () => $client
        );
    }
}
