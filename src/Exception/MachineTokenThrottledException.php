<?php

namespace Wexample\SymfonyApi\Exception;

use DateTimeImmutable;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Wexample\SymfonyApi\Interface\MachineClientInterface;

/**
 * A machine request over one of the firewall's limits: `client` for an
 * authenticated client sending too much, `ip` for an address failing too often.
 */
class MachineTokenThrottledException extends AuthenticationException
{
    final public const string LIMIT_CLIENT = 'client';

    final public const string LIMIT_IP = 'ip';

    public function __construct(
        public readonly string $limit,
        public readonly DateTimeImmutable $retryAfter,
        public readonly ?MachineClientInterface $client = null,
    ) {
        parent::__construct('Too many requests.');
    }
}
