<?php

namespace Wexample\SymfonyApi\Tests\Fixtures\App\Normalizer;

use ArrayObject;
use Wexample\SymfonyApi\Tests\Fixtures\App\Entity\Vehicle;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonyHelpers\Interface\NormalizableDataInterface;
use Wexample\SymfonyHelpers\Normalizer\AbstractEntityNormalizer;

class VehicleNormalizer extends AbstractEntityNormalizer
{
    public static function getEntityClassName(): string
    {
        return Vehicle::class;
    }

    public function normalizeEntity(
        Vehicle|AbstractEntity $entity,
        ?string $format = null,
        array $context = []
    ): array|string|int|float|bool|ArrayObject|NormalizableDataInterface|null {
        return [
            'id' => (string) $entity->getId(),
            'name' => $entity->name,
        ];
    }
}
