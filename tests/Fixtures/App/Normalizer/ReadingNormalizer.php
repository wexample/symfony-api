<?php

namespace Wexample\SymfonyApi\Tests\Fixtures\App\Normalizer;

use ArrayObject;
use Wexample\SymfonyApi\Tests\Fixtures\App\Entity\Reading;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonyHelpers\Interface\NormalizableDataInterface;
use Wexample\SymfonyHelpers\Normalizer\AbstractEntityNormalizer;

class ReadingNormalizer extends AbstractEntityNormalizer
{
    public static function getEntityClassName(): string
    {
        return Reading::class;
    }

    public function normalizeEntity(
        Reading|AbstractEntity $entity,
        ?string $format = null,
        array $context = []
    ): array|string|int|float|bool|ArrayObject|NormalizableDataInterface|null {
        return [
            'id' => (string) $entity->getId(),
            'value' => $entity->value,
            'code' => $entity->code,
        ];
    }
}
