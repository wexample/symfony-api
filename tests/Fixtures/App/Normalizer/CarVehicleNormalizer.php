<?php

namespace Wexample\SymfonyApi\Tests\Fixtures\App\Normalizer;

use ArrayObject;
use Wexample\SymfonyApi\Tests\Fixtures\App\Entity\CarVehicle;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonyHelpers\Interface\NormalizableDataInterface;

/**
 * What a car says beyond what every vehicle does.
 */
class CarVehicleNormalizer extends VehicleNormalizer
{
    public static function getEntityClassName(): string
    {
        return CarVehicle::class;
    }

    public function normalizeEntity(
        CarVehicle|AbstractEntity $entity,
        ?string $format = null,
        array $context = []
    ): array|string|int|float|bool|ArrayObject|NormalizableDataInterface|null {
        return parent::normalizeEntity($entity, $format, $context) + ['seats' => $entity->seats];
    }
}
