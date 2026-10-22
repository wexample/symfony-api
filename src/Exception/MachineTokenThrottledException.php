<?php

namespace Wexample\SymfonyApi\Exception;

use DateTimeImmutable;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * A token request over one of the firewall's limits: `client` for an
 * authenticated holder sending too much, `ip` for an address failing too often.
 */
class MachineTokenThrottledException extends AuthenticationException
{
    final public const string LIMIT_CLIENT = 'client';

    final public const string LIMIT_IP = 'ip';

    public function __construct(
        public readonly string $limit,
        public readonly DateTimeImmutable $retryAfter,
        public readonly ?UserInterface $client = null,
    ) {
        parent::__construct('Too many requests.');
    }
}
