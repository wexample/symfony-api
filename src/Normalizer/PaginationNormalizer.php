<?php

namespace Wexample\SymfonyApi\Normalizer;

use ArrayObject;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class PaginationNormalizer implements NormalizerInterface
{
    public function normalizePagination(
        int $page,
        ?int $length,
        array $items
    ) {
        return [
            'pagination' => [
                'page' => $page,
                'length' => $length,
            ],
            'items' => $items,
        ];
    }

    /**
     * @param array       $object
     * @param string|null $format
     * @param array       $context
     * @return array|string|int|float|bool|ArrayObject|null
     */
    public function normalize(
        mixed $object,
        ?string $format = null,
        array $context = []
    ): array|string|int|float|bool|ArrayObject|null {
        return $this->normalizePagination(
            page: $object["page"] ?? 0,
            length: $object["length"] ?? 10,
            items: $object["items"] ?? []
        );
    }

    public function supportsNormalization(
        mixed $data,
        string $format = null,
        array $context = []
    ): bool {
        return true;
    }

    public function getSupportedTypes(?string $format): array
    {
        return ['array' => true];
    }
}