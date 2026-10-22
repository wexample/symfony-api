<?php

namespace Wexample\SymfonyApi\Tests\Fixtures\App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Wexample\SymfonyApi\Entity\AbstractMachineToken;
use Wexample\SymfonyApi\Interface\MachineClientInterface;

#[ORM\Entity]
class DeviceToken extends AbstractMachineToken
{
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Device $device;

    public function getClient(): Device
    {
        return $this->device;
    }

    public function setClient(MachineClientInterface $client): static
    {
        $this->device = $client;

        return $this;
    }
}
