<?php

namespace Wexample\SymfonyApi\Service;

use DateInterval;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Wexample\SymfonyApi\Entity\AbstractUserToken;
use Wexample\SymfonyApi\Enum\UserTokenSecurityEventType;
use Wexample\SymfonyApi\Interface\MachineClientInterface;

/**
 * The tokens of people, configured under `user_token`. A token may be
 * limited to some of its holder's roles, its scopes.
 */
class UserTokenService extends AbstractApiTokenService
{
    public function __construct(
        EntityManagerInterface $entityManager,
        MachineSecurityJournalService $journal,
        private readonly RoleHierarchyInterface $roleHierarchy,
        #[Autowire(param: 'api_user_token_class')]
        ?string $tokenClass,
        #[Autowire(param: 'api_user_token_prefix')]
        string $prefix,
        #[Autowire(param: 'api_user_token_max_lifetime')]
        ?string $maxLifetime,
    ) {
        parent::__construct($entityManager, $journal, $tokenClass, $prefix, $maxLifetime);
    }

    /**
     * @param list<string>|null $scopes roles of the holder the token is limited to; null for all
     */
    public function issue(
        UserInterface $client,
        ?DateTimeImmutable $dateExpiration = null,
        ?string $label = null,
        ?array $scopes = null
    ): string {
        $scopes = $this->checkScopes($client, $scopes);

        return $this->issueToken(
            $client,
            $dateExpiration,
            $label,
            fn (AbstractUserToken $token) => $token->setScopes($scopes),
            ['scopes' => null === $scopes ? null : implode(',', $scopes)]
        );
    }

    /**
     * @param list<string>|null $scopes roles of the holder the new token is limited to; null for all
     */
    public function rotate(
        UserInterface $client,
        DateInterval $overlap,
        ?DateTimeImmutable $dateExpiration = null,
        ?string $label = null,
        ?array $scopes = null
    ): string {
        $scopes = $this->checkScopes($client, $scopes);

        return $this->rotateToken(
            $client,
            $overlap,
            $dateExpiration,
            $label,
            fn (AbstractUserToken $token) => $token->setScopes($scopes),
            ['scopes' => null === $scopes ? null : implode(',', $scopes)]
        );
    }

    /**
     * The roles the token authenticates with: the scopes still within its
     * holder's reach, the hierarchy included — a role the holder lost since
     * is dropped.
     *
     * @return list<string>|null null for all the holder's roles
     */
    public function resolveRoles(AbstractUserToken $token): ?array
    {
        if (null === $token->getScopes()) {
            return null;
        }

        return array_values(array_intersect($token->getScopes(), $this->getReachableRoles($token->getClient())));
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

    /**
     * @param list<string>|null $scopes
     * @return list<string>|null
     */
    private function checkScopes(UserInterface $client, ?array $scopes): ?array
    {
        if (null === $scopes) {
            return null;
        }

        $scopes = array_values(array_unique($scopes));
        $foreign = array_diff($scopes, $this->getReachableRoles($client));

        // A token never opens more than its holder.
        if (! empty($foreign)) {
            throw new InvalidArgumentException('Not a role of ' . $client->getUserIdentifier() . ': ' . implode(', ', $foreign) . '.');
        }

        return $scopes;
    }

    /**
     * @return list<string>
     */
    private function getReachableRoles(UserInterface $client): array
    {
        return $this->roleHierarchy->getReachableRoleNames($client->getRoles());
    }
}
