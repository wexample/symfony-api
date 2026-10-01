<?php

namespace Wexample\SymfonyApi\Event;

use DateTimeImmutable;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * One batch received: who sent it, what for, how many items of each outcome.
 * The items themselves belong to the application.
 */
class ApiBatchEvent extends Event
{
    /**
     * @param array<string, int> $summary
     */
    public function __construct(
        public readonly DateTimeImmutable $occurredAt,
        public readonly string $scope,
        public readonly string $itemClass,
        public readonly array $summary,
        public readonly ?string $route = null,
        public readonly ?string $requestId = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'scope' => $this->scope,
            'item_class' => $this->itemClass,
            'summary' => $this->summary,
            'route' => $this->route,
            'request_id' => $this->requestId,
            'occurred_at' => $this->occurredAt->format(DATE_ATOM),
        ];
    }
}
