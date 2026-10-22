<?php

namespace Wexample\SymfonyApi\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * One token of a person, for the scripts they run in their own name: it
 * authenticates as that user and opens what their roles open — or, when it
 * has scopes, what those of their roles open. The application's subclass
 * maps the relation to its user entity.
 */
abstract class AbstractUserToken extends AbstractApiToken
{
    /**
     * The roles the token is limited to, among those of its holder; null
     * for all of them.
     *
     * @var list<string>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    protected ?array $scopes = null;

    abstract public function setClient(UserInterface $client): static;

    /**
     * @return list<string>|null
     */
    public function getScopes(): ?array
    {
        return $this->scopes;
    }

    /**
     * @param list<string>|null $scopes
     */
    public function setScopes(?array $scopes): self
    {
        $this->scopes = $scopes;

        return $this;
    }
}
