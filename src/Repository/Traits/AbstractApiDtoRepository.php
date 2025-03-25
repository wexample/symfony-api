<?php

namespace Wexample\SymfonyApi\Repository\Traits;

use Exception;
use Wexample\SymfonyApi\Api\Dto\AbstractDto;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonyHelpers\Entity\Traits\Manipulator\EntityManipulatorTrait;
use Wexample\Helpers\Helper\ClassHelper;

trait AbstractApiDtoRepository
{
    use EntityManipulatorTrait;

    /**
     * Updates the entity by calling the appropriate updateByDto method
     * based on the class type of the provided entity.
     *
     * @param AbstractEntity $entity
     * @param mixed $dto
     * @param string $methodPrefix
     * @param bool $flush
     * @return AbstractEntity
     * @throws Exception
     */
    public function updateByDtoAndClassType(
        AbstractEntity $entity,
        AbstractDto $dto,
        string $methodPrefix = 'updateByDto',
        bool $flush = true
    ): AbstractEntity {
        $entityShortName = ClassHelper::getShortName($entity);

        $updateMethod = $methodPrefix.$entityShortName;

        if (!method_exists($this, $updateMethod)) {
            throw new \Exception("Update method \"$updateMethod\" not found on repository \"".static::class."\".");
        }

        $updatedEntity = $this->$updateMethod($entity, $dto);

        $this->save(
            entity: $updatedEntity,
            flush: $flush
        );

        return $updatedEntity;
    }
}