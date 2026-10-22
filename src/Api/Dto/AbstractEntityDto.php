<?php

namespace Wexample\SymfonyApi\Api\Dto;

use Wexample\SymfonyHelpers\Entity\AbstractEntity;

abstract class AbstractEntityDto extends AbstractDto
{
    public string $id;

    public static function fromEntity(AbstractEntity $entity): self
    {
        $dto = new static();
        $dto->id = (string) $entity->getId();

        return $dto;
    }
}
