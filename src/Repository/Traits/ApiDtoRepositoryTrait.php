<?php

namespace Wexample\SymfonyApi\Repository\Traits;

use Exception;
use Wexample\Helpers\Helper\ClassHelper;
use Wexample\SymfonyApi\Api\Dto\AbstractDto;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonyHelpers\Entity\Traits\Manipulator\EntityManipulatorTrait;

trait ApiDtoRepositoryTrait
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
        // The method written for the class, or else for an ancestor: what
        // updates a session updates every kind of session that has no update
        // of its own.
        $updateMethod = null;

        for ($class = new \ReflectionClass(ClassHelper::getRealClassPath($entity)); $class; $class = $class->getParentClass()) {
            if (method_exists($this, $methodPrefix.$class->getShortName())) {
                $updateMethod = $methodPrefix.$class->getShortName();

                break;
            }
        }

        if (! $updateMethod) {
            throw new \Exception("Update method \"".$methodPrefix.ClassHelper::getShortName($entity)."\" not found on repository \"".static::class."\".");
        }

        $updatedEntity = $this->$updateMethod($entity, $dto);

        $this->save(
            entity: $updatedEntity,
            flush: $flush
        );

        return $updatedEntity;
    }
}
