<?php

namespace Wexample\SymfonyApi\Api\Dto;

use Wexample\SymfonyApi\Api\Attribute\QueryOption\LengthQueryOption;

/**
 * Pages are zero indexed, matching PageQueryOption default.
 * A null length means "no limit", a null total means the count was not computed.
 */
class PaginationDto
{
    public readonly int $page;

    public function __construct(
        int $page = 0,
        public readonly ?int $length = LengthQueryOption::DEFAULT_PAGE_LENGTH,
        public readonly ?int $total = null,
    ) {
        // A negative page counts back from the end, -1 being the last one. A caller
        // reading a collection newest first cannot know how many pages there are
        // before it has asked, and this is how it asks.
        $this->page = max(0, $page < 0 ? ($this->getPagesCount() ?? 1) + $page : $page);
    }

    public function withTotal(?int $total): self
    {
        return new self(
            page: $this->page,
            length: $this->length,
            total: $total
        );
    }

    public function getOffset(): int
    {
        return $this->length ? $this->page * $this->length : 0;
    }

    public function getPagesCount(): ?int
    {
        if ($this->total === null) {
            return null;
        }

        if (! $this->length) {
            return 1;
        }

        return (int) ceil($this->total / $this->length);
    }

    public function hasMore(): ?bool
    {
        if ($this->total === null) {
            return null;
        }

        if (! $this->length) {
            return false;
        }

        return $this->getOffset() + $this->length < $this->total;
    }

    public function toArray(): array
    {
        return [
            'page' => $this->page,
            'length' => $this->length,
            'total' => $this->total,
            'pagesCount' => $this->getPagesCount(),
            'hasMore' => $this->hasMore(),
        ];
    }
}
