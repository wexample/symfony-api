<?php

namespace Wexample\SymfonyApi\Api\Dto;

use Wexample\SymfonyHelpers\Entity\AbstractEntity;

abstract class AbstractEntityDto extends AbstractDto
{
    public string $secureId;

    public static function fromEntity(AbstractEntity $entity): self
    {
        $dto = new static();
        $dto->secureId = $entity->getSecureId();
        return $dto;
    }
}
