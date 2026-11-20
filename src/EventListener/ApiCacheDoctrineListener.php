<?php

namespace Wexample\SymfonyApi\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Wexample\SymfonyApi\DependencyInjection\Compiler\ApiCacheEntitiesCompilerPass;
use Wexample\SymfonyApi\Service\ApiCacheService;

/**
 * Drops the api's answers kept from an entity as soon as one is written:
 * the classes inserted, updated or deleted by a flush, and their parents —
 * an answer may name the parent —, among those some #[ApiCache] route is
 * read from. Done at the flush itself, nothing kept between two: a flush
 * that fails afterwards has only cost answers to compute again.
 */
#[AsDoctrineListener(event: Events::onFlush)]
class ApiCacheDoctrineListener
{
    /**
     * @param list<class-string> $cachedEntities
     */
    public function __construct(
        private readonly ApiCacheService $cache,
        #[Autowire(param: ApiCacheEntitiesCompilerPass::PARAMETER)]
        private readonly array $cachedEntities,
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        if ([] === $this->cachedEntities) {
            return;
        }

        $unitOfWork = $args->getObjectManager()->getUnitOfWork();
        $classes = [];

        foreach ([
            ...$unitOfWork->getScheduledEntityInsertions(),
            ...$unitOfWork->getScheduledEntityUpdates(),
            ...$unitOfWork->getScheduledEntityDeletions(),
        ] as $entity) {
            for ($class = $entity::class; false !== $class; $class = get_parent_class($class)) {
                $classes[$class] = true;
            }
        }

        $this->cache->invalidateEntities(array_values(array_intersect(array_keys($classes), $this->cachedEntities)));
    }
}
