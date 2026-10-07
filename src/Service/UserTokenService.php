<?php

namespace Wexample\SymfonyApi\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\User\UserInterface;
use Wexample\SymfonyApi\Enum\UserTokenSecurityEventType;
use Wexample\SymfonyApi\Interface\MachineClientInterface;

/**
 * The tokens of people, configured under `user_token`.
 */
class UserTokenService extends AbstractApiTokenService
{
    public function __construct(
        EntityManagerInterface $entityManager,
        MachineSecurityJournalService $journal,
        #[Autowire(param: 'api_user_token_class')]
        ?string $tokenClass,
        #[Autowire(param: 'api_user_token_prefix')]
        string $prefix,
    ) {
        parent::__construct($entityManager, $journal, $tokenClass, $prefix);
    }

    /**
     * A machine client implements UserInterface too: its tokens are the
     * machine kind's, bound to the machine roles.
     */
    public function canHold(UserInterface $client): bool
    {
        return ! $client instanceof MachineClientInterface && parent::canHold($client);
    }

    protected function getClientInterface(): string
    {
        return UserInterface::class;
    }

    protected function getEventTypeClass(): string
    {
        return UserTokenSecurityEventType::class;
    }

    protected function getConfigurationKey(): string
    {
        return 'user_token';
    }
}
