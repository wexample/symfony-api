<?php

namespace Wexample\SymfonyApi\Tests\Fixtures\App\Controller;

use Doctrine\ORM\EntityRepository;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Wexample\SymfonyApi\Api\Controller\AbstractApiController;
use Wexample\SymfonyApi\Api\Controller\Traits\EntityListApiControllerTrait;
use Wexample\SymfonyApi\Tests\Fixtures\App\Normalizer\ReadingNormalizer;
use Wexample\SymfonyApi\Tests\Fixtures\App\Repository\ReadingRepository;

/**
 * A list left unscoped: every device's readings, which the voter refuses.
 */
#[Route(path: '/api/device/reading-unscoped/', name: 'reading_unscoped_')]
class UnscopedReadingApiController extends AbstractApiController
{
    use EntityListApiControllerTrait;

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
}
