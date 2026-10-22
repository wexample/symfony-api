<?php

namespace Wexample\SymfonyApi\Api\Dto;

use Symfony\Component\Validator\Constraints\Type;
use Wexample\SymfonyApi\Api\Attribute\RequiredDtoProperty;
use Wexample\SymfonyHelpers\Helper\VariableHelper;

abstract class AbstractEntityCollectionDto extends AbstractCollectionDto
{
    /** @var AbstractEntityDto[] */
    #[Type(VariableHelper::VARIABLE_TYPE_ARRAY)]
    #[RequiredDtoProperty]
    public array $entities = [];

    abstract public static function getEntityDtoClass(): string;

    public static function getCollectionKey(): string
    {
        return 'entities';
    }

    public static function getCollectionItemDtoClass(): string
    {
        return static::getEntityDtoClass();
    }

    /**
     * @param AbstractEntityDto[] $entities
     */
    public function setEntities(array $entities): void
    {
        $this->entities = $entities;
    }
}
