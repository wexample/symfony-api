<?php

namespace Wexample\SymfonyApi\Security;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Wexample\SymfonyApi\DependencyInjection\WexampleSymfonyApiExtension;
use Wexample\SymfonyApi\Entity\AbstractApiToken;
use Wexample\SymfonyApi\Enum\MachineSecurityEventType;
use Wexample\SymfonyApi\Enum\MachineTokenRefusalCause;
use Wexample\SymfonyApi\Exception\MachineTokenRefusedException;
use Wexample\SymfonyApi\Service\MachineTokenService;

/**
 * The `token_handler` of a machine firewall: a token of a machine client,
 * which carries nothing but the machine roles.
 */
class MachineTokenHandler extends AbstractApiTokenHandler
{
    /**
     * @param list<string> $allowedRoles
     */
    public function __construct(
        MachineTokenService $machineTokenService,
        RequestStack $requestStack,
        private readonly LoggerInterface $logger,
        #[Autowire(param: 'api_machine_token_prefix')]
        string $prefix,
        #[Autowire(param: 'api_machine_token_roles')]
        private readonly array $allowedRoles,
        #[Autowire(param: 'api_machine_token_last_used_interval')]
        int $lastUsedInterval,
        #[Autowire(param: 'api_machine_token_rate_limit_enabled')]
        bool $rateLimitEnabled,
        #[Autowire(service: 'limiter.' . WexampleSymfonyApiExtension::LIMITER_MACHINE_CLIENT)]
        RateLimiterFactoryInterface $clientLimiter,
        #[Autowire(service: 'limiter.' . WexampleSymfonyApiExtension::LIMITER_MACHINE_IP_FAILURES)]
        RateLimiterFactoryInterface $ipFailuresLimiter,
    ) {
        parent::__construct(
            $machineTokenService,
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
        return MachineSecurityEventType::class;
    }

    protected function checkHolder(AbstractApiToken $token, ?string $hint): void
    {
        $client = $token->getClient();
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
    }
}
