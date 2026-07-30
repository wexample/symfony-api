<?php

namespace Wexample\SymfonyApi\Normalizer;

use ArrayObject;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Wexample\SymfonyApi\Api\Dto\PaginationDto;

class PaginationNormalizer implements NormalizerInterface
{
    /**
     * @param PaginationDto $data
     */
    public function normalize(
        mixed $data,
        ?string $format = null,
        array $context = []
    ): array|string|int|float|bool|ArrayObject|null {
        return $data->toArray();
    }

    public function supportsNormalization(
        mixed $data,
        ?string $format = null,
        array $context = []
    ): bool {
        return $data instanceof PaginationDto;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [PaginationDto::class => true];
    }
}
