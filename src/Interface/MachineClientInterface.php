<?php

namespace Wexample\SymfonyApi\Interface;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * A program calling the API in its own name — a device, a server — rather than
 * a person: no password, no session, one or several tokens of its own.
 *
 * The application's client entity implements it, and is never registered in
 * the user provider of the page firewall.
 */
interface MachineClientInterface extends UserInterface
{
    public const string ROLE = 'ROLE_MACHINE';
}
