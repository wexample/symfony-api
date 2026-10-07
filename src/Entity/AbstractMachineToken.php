<?php

namespace Wexample\SymfonyApi\Entity;

use Wexample\SymfonyApi\Interface\MachineClientInterface;

/**
 * One token of a machine client — a device, a server. The application's
 * subclass maps the relation to its client entity.
 */
abstract class AbstractMachineToken extends AbstractApiToken
{
    abstract public function getClient(): MachineClientInterface;

    abstract public function setClient(MachineClientInterface $client): static;
}
