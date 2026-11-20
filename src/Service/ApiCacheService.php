<?php

namespace Wexample\SymfonyApi\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * The answers of the api kept on the server (ApiCache), tagged with the
 * entities they are read from, dropped when one of them is written.
 */
class ApiCacheService
{
    public function __construct(
        #[Autowire(service: 'cache.app.taggable')]
        private readonly TagAwareCacheInterface $pool,
    ) {
    }

    public function getPool(): TagAwareCacheInterface
    {
        return $this->pool;
    }

    /**
     * The tag of the answers read from a class: a cache tag holds no
     * backslash.
     */
    public static function getEntityTag(string $class): string
    {
        return 'api_entity.' . str_replace('\\', '.', ltrim($class, '\\'));
    }

    /**
     * @param list<class-string> $classes
     */
    public function invalidateEntities(array $classes): void
    {
        if ([] !== $classes) {
            $this->pool->invalidateTags(array_map(self::getEntityTag(...), $classes));
        }
    }
}
