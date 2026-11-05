<?php

namespace Wexample\SymfonyApi\Tests\Fixtures\App\Repository;

use Wexample\SymfonyApi\Repository\Traits\ApiDtoRepositoryTrait;
use Wexample\SymfonyApi\Tests\Fixtures\App\Dto\ReadingDto;
use Wexample\SymfonyApi\Tests\Fixtures\App\Entity\Reading;
use Wexample\SymfonyHelpers\Repository\AbstractRepository;

class ReadingRepository extends AbstractRepository
{
    use ApiDtoRepositoryTrait;

    public static function getEntityClassName(): string
    {
        return Reading::class;
    }

    public function updateByDtoReading(
        Reading $reading,
        ReadingDto $dto
    ): Reading {
        $reading->value = $dto->value;
        $reading->code = $dto->code;

        return $reading;
    }
}
