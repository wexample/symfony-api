<?php

namespace Wexample\SymfonyApi\Tests\Fixtures\App\Normalizer;

use ArrayObject;
use Wexample\SymfonyApi\Tests\Fixtures\App\Entity\DeviceFile;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonyHelpers\Interface\NormalizableDataInterface;
use Wexample\SymfonyHelpers\Normalizer\AbstractEntityNormalizer;

class DeviceFileNormalizer extends AbstractEntityNormalizer
{
    public static function getEntityClassName(): string
    {
        return DeviceFile::class;
    }

    public function normalizeEntity(
        DeviceFile|AbstractEntity $entity,
        ?string $format = null,
        array $context = []
    ): array|string|int|float|bool|ArrayObject|NormalizableDataInterface|null {
        return [
            'id' => (string) $entity->getId(),
            'name' => $entity->name,
            'size' => $entity->size,
            'dateModified' => $this->normalizeDateTimeOrNull($entity->dateModified, $context),
        ];
    }
}
