<?php

namespace Wexample\SymfonyApi\Tests\Fixtures\App\Controller;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Wexample\SymfonyApi\Api\Controller\AbstractApiController;
use Wexample\SymfonyApi\Api\Controller\Traits\EntityDeleteApiControllerTrait;
use Wexample\SymfonyApi\Api\Controller\Traits\EntityListApiControllerTrait;
use Wexample\SymfonyApi\Api\Controller\Traits\EntityShowApiControllerTrait;
use Wexample\SymfonyApi\Api\Controller\Traits\EntityUpdateApiControllerTrait;
use Wexample\SymfonyApi\Tests\Fixtures\App\Dto\ReadingDto;
use Wexample\SymfonyApi\Tests\Fixtures\App\Entity\Reading;
use Wexample\SymfonyApi\Tests\Fixtures\App\Normalizer\ReadingNormalizer;
use Wexample\SymfonyApi\Tests\Fixtures\App\Repository\ReadingRepository;

/**
 * A device's readings through the entity actions: listed scoped to it, the
 * rest left to the voter.
 */
#[Route(path: '/api/device/reading/', name: 'reading_')]
class ReadingApiController extends AbstractApiController
{
    use EntityListApiControllerTrait;
    use EntityShowApiControllerTrait;
    use EntityUpdateApiControllerTrait;
    use EntityDeleteApiControllerTrait;

    final public const string DTO_UPDATE = ReadingDto::class;

    final public const string CODE_KEPT = 'kept';

    public function __construct(
        private readonly ReadingRepository $repository,
        private readonly ReadingNormalizer $normalizer,
    ) {
    }

    protected function getApiEntityRepository(): EntityRepository
    {
        return $this->repository;
    }

    protected function getApiEntityNormalizer(): NormalizerInterface
    {
        return $this->normalizer;
    }

    protected function createApiEntityListQueryBuilder(): QueryBuilder
    {
        return $this->repository->createQueryBuilder('reading')
            ->join('reading.device', 'device')
            ->where('device.id = :device')
            // Typed: SQLite stores the UUID as binary.
            ->setParameter('device', $this->getUser()->getId(), UuidType::NAME)
            ->orderBy('reading.code', 'ASC');
    }

    protected function getApiEntityRemovalRefusal(object $entity): ?string
    {
        return self::CODE_KEPT === $entity->code ? 'Kept: a kept reading stays.' : null;
    }
}
