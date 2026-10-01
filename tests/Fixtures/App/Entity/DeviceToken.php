<?php

namespace Wexample\SymfonyApi\Tests\Fixtures\App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Wexample\SymfonyApi\Entity\AbstractMachineToken;

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

    public function setDevice(Device $device): self
    {
        $this->device = $device;

        return $this;
    }
}
