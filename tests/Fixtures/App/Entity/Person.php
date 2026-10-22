<?php

namespace Wexample\SymfonyApi\Tests\Fixtures\App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;

/**
 * A person of the application, signing in to the pages and running scripts
 * with tokens of their own.
 */
#[ORM\Entity]
class Person extends AbstractEntity implements UserInterface
{
    #[ORM\Column(type: Types::STRING, length: 180, unique: true)]
    private string $email;

    /**
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $roles = ['ROLE_USER'];

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $enabled = true;

    public function __construct(string $email, array $roles = ['ROLE_USER'])
    {
        parent::__construct();

        $this->email = $email;
        $this->roles = $roles;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function eraseCredentials(): void
    {
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;

        return $this;
    }
}
