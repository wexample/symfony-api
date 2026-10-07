<?php

namespace Wexample\SymfonyApi\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Wexample\SymfonyApi\Enum\MachineSecurityEventType;
use Wexample\SymfonyApi\Interface\MachineClientInterface;

/**
 * The tokens of machine clients, configured under `machine_token`.
 */
class MachineTokenService extends AbstractApiTokenService
{
    public function __construct(
        EntityManagerInterface $entityManager,
        MachineSecurityJournalService $journal,
        #[Autowire(param: 'api_machine_token_class')]
        ?string $tokenClass,
        #[Autowire(param: 'api_machine_token_prefix')]
        string $prefix,
    ) {
        parent::__construct($entityManager, $journal, $tokenClass, $prefix);
    }

    protected function getClientInterface(): string
    {
        return MachineClientInterface::class;
    }

    protected function getEventTypeClass(): string
    {
        return MachineSecurityEventType::class;
    }

    protected function getConfigurationKey(): string
    {
        return 'machine_token';
    }
}
