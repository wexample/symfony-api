<?php

namespace Wexample\SymfonyApi\Api\Class;

final class ApiSuccessCollectionData
{
    /** @var array<int, mixed> */
    private array $items = [];

    /** @var array<string, mixed> */
    private array $extra = [];

    /**
     * @param array<int, mixed> $items
     * @param array<string, mixed> $extra
     */
    public function __construct(
        array $items = [],
        array $extra = []
    ) {
        $this->items = $items;
        $this->extra = $extra;
    }

    /**
     * @param array<int, mixed> $items
     * @param array<string, mixed> $extra
     */
    public static function create(
        array $items = [],
        array $extra = []
    ): self {
        return new self(
            items: $items,
            extra: $extra
        );
    }

    /**
     * @param array<int, mixed> $items
     */
    public function setItems(array $items): self
    {
        $this->items = $items;

        return $this;
    }

    public function addItem(mixed $item): self
    {
        $this->items[] = $item;

        return $this;
    }

    /**
     * @param array<string, mixed> $extra
     */
    public function setExtra(array $extra): self
    {
        $this->extra = $extra;

        return $this;
    }

    public function setValue(string $key, mixed $value): self
    {
        $this->extra[$key] = $value;

        return $this;
    }

    /**
     * @return array{items: array<int, mixed>}|array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'items' => $this->items,
        ] + $this->extra;
    }
}
