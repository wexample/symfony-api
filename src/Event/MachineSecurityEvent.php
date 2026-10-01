<?php

namespace Wexample\SymfonyApi\Event;

use DateTimeImmutable;
use Symfony\Contracts\EventDispatcher\Event;
use Wexample\SymfonyApi\Enum\MachineSecurityEventType;

/**
 * A machine token fact, dispatched by MachineSecurityJournalService for
 * whoever records it: the package's own log subscriber, an audit package.
 *
 * Same shape as the SecurityEvent of symfony-user, so one recorder reads both:
 * `user_id` holds the machine client. It never holds a secret or a hash —
 * a token is named by its hint, in `extra.token_hint`.
 */
class MachineSecurityEvent extends Event
{
    /**
     * @param array<string, scalar|null> $extra
     */
    public function __construct(
        public readonly MachineSecurityEventType $type,
        public readonly DateTimeImmutable $occurredAt,
        public readonly ?string $userId = null,
        public readonly ?string $cause = null,
        public readonly ?string $method = null,
        public readonly ?string $firewall = null,
        public readonly ?string $ip = null,
        public readonly ?string $userAgent = null,
        public readonly ?string $requestId = null,
        public readonly array $extra = [],
    ) {
    }

    /**
     * @return array<string, scalar|array|null>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'outcome' => $this->type->isFailure() ? 'failure' : 'success',
            'cause' => $this->cause,
            'user_id' => $this->userId,
            'method' => $this->method,
            'firewall' => $this->firewall,
            'ip' => $this->ip,
            'user_agent' => $this->userAgent,
            'request_id' => $this->requestId,
            'occurred_at' => $this->occurredAt->format(DATE_ATOM),
            'extra' => $this->extra,
        ];
    }
}
