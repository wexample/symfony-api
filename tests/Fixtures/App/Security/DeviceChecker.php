<?php

namespace Wexample\SymfonyApi\Tests\Fixtures\App\Security;

use Symfony\Component\Security\Core\Exception\DisabledException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Wexample\SymfonyApi\Tests\Fixtures\App\Entity\Device;
use Wexample\SymfonyApi\Tests\Fixtures\App\Entity\Person;

/**
 * How an application switches a client — a device, a person — off without
 * touching its tokens.
 */
class DeviceChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (($user instanceof Device || $user instanceof Person) && ! $user->isEnabled()) {
            $exception = new DisabledException('Account disabled.');
            $exception->setUser($user);

            throw $exception;
        }
    }

    public function checkPostAuth(UserInterface $user, mixed ...$arguments): void
    {
    }
}
