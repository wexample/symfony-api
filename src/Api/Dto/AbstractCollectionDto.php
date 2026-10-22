<?php

namespace Wexample\SymfonyApi\Api\Dto;

abstract class AbstractCollectionDto extends AbstractDto
{
    public static function getCollectionKey(): string
    {
        return 'items';
    }

    abstract public static function getCollectionItemDtoClass(): string;

    public function setCollectionItems(array $items): void
    {
        $key = static::getCollectionKey();
        $this->$key = $items;
    }

    public function getCollectionItems(): array
    {
        $key = static::getCollectionKey();
        $items = $this->$key ?? [];

        return is_array($items) ? $items : [];
    }
}
