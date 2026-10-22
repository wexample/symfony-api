<?php

namespace Wexample\SymfonyApi\Security;

use DateTimeImmutable;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Wexample\SymfonyApi\Entity\AbstractApiToken;
use Wexample\SymfonyApi\Enum\MachineSecurityEventType;
use Wexample\SymfonyApi\Enum\MachineTokenRefusalCause;
use Wexample\SymfonyApi\Enum\UserTokenSecurityEventType;
use Wexample\SymfonyApi\Exception\MachineTokenRefusedException;
use Wexample\SymfonyApi\Exception\MachineTokenThrottledException;
use Wexample\SymfonyApi\Helper\MachineTokenHelper;
use Wexample\SymfonyApi\Service\AbstractApiTokenService;

/**
 * Resolves the bearer token of a token firewall to its holder, for the native
 * `access_token` authenticator of Symfony. One subclass per kind of token.
 *
 * Unknown, revoked and expired tokens fail the same way: the response tells a
 * caller nothing about which tokens exist. The cause travels in the exception,
 * for the journal.
 */
abstract class AbstractApiTokenHandler implements AccessTokenHandlerInterface
{
    /**
     * The token entity once found, for ApiTokenSecuritySubscriber.
     */
    public const string REQUEST_ATTRIBUTE_TOKEN = '_wexample_api_token';

    /**
     * Set as soon as a handler reads a token: marks the request as a token
     * authentication attempt, and names the token for the journal.
     */
    public const string REQUEST_ATTRIBUTE_HINT = '_wexample_api_token_hint';

    /**
     * The handler that read the token: what follows the attempt is done
     * after its kind's settings.
     */
    public const string REQUEST_ATTRIBUTE_HANDLER = '_wexample_api_token_handler';

    public function __construct(
        protected readonly AbstractApiTokenService $tokenService,
        protected readonly RequestStack $requestStack,
        protected readonly string $prefix,
        protected readonly int $lastUsedInterval,
        protected readonly bool $rateLimitEnabled,
        protected readonly RateLimiterFactoryInterface $clientLimiter,
        protected readonly RateLimiterFactoryInterface $ipFailuresLimiter,
    ) {
    }

    /**
     * @return class-string<MachineSecurityEventType|UserTokenSecurityEventType>
     */
    abstract public function getEventTypeClass(): string;

    /**
     * Refuses a found, usable token whose holder this kind does not admit.
     *
     * @throws MachineTokenRefusedException
     */
    protected function checkHolder(AbstractApiToken $token, ?string $hint): void
    {
    }

    public function getLastUsedInterval(): int
    {
        return $this->lastUsedInterval;
    }

    public function isRateLimitEnabled(): bool
    {
        return $this->rateLimitEnabled;
    }

    public function getIpFailuresLimiter(): RateLimiterFactoryInterface
    {
        return $this->ipFailuresLimiter;
    }

    public function getUserBadgeFrom(string $accessToken): UserBadge
    {
        // A string that is not one of ours may be someone else's secret: no
        // part of it goes to the journal.
        $hint = str_starts_with($accessToken, $this->prefix)
            ? MachineTokenHelper::buildHint($accessToken, $this->prefix)
            : null;

        $request = $this->requestStack->getMainRequest();
        $request?->attributes->set(self::REQUEST_ATTRIBUTE_HINT, $hint);
        $request?->attributes->set(self::REQUEST_ATTRIBUTE_HANDLER, $this);

        // An address that failed too often is stopped before the database.
        if ($this->rateLimitEnabled && $request) {
            $limit = $this->ipFailuresLimiter->create($request->getClientIp())->consume(0);

            if (0 === $limit->getRemainingTokens()) {
                throw new MachineTokenThrottledException(MachineTokenThrottledException::LIMIT_IP, $limit->getRetryAfter());
            }
        }

        $token = $this->tokenService->findTokenBySecret($accessToken);
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

        $this->checkHolder($token, $hint);

        if ($this->rateLimitEnabled) {
            $limit = $this->clientLimiter->create($client->getUserIdentifier())->consume();

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
