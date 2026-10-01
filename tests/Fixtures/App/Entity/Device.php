<?php

namespace Wexample\SymfonyApi\Tests\Fixtures\App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Wexample\SymfonyApi\Interface\MachineClientInterface;
use Wexample\SymfonyApi\Traits\MachineClientTrait;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;

#[ORM\Entity]
class Device extends AbstractEntity implements MachineClientInterface
{
    use MachineClientTrait {
        getRoles as getMachineRoles;
    }

    /**
     * Lets a test hand a device a role it must never carry.
     *
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $extraRoles = [];

    public function getUserIdentifier(): string
    {
        return (string) $this->getId();
    }

    public function getRoles(): array
    {
        return [...$this->getMachineRoles(), ...$this->extraRoles];
    }

    public function setExtraRoles(array $extraRoles): self
    {
        $this->extraRoles = $extraRoles;

        return $this;
    }
}
