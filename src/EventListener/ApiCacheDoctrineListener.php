<?php

namespace Wexample\SymfonyApi\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Wexample\SymfonyApi\Service\ApiCacheService;

/**
 * Drops the api's answers kept from an entity as soon as one is written:
 * the classes inserted, updated or deleted by a flush, and their parents —
 * an answer may name the parent. Done at the flush itself, nothing kept
 * between two: a flush that fails afterwards has only cost answers to
 * compute again.
 */
#[AsDoctrineListener(event: Events::onFlush)]
class ApiCacheDoctrineListener
{
    public function __construct(
        private readonly ApiCacheService $cache,
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
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

        $this->cache->invalidateEntities(array_keys($classes));
    }
}
