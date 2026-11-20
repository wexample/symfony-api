<?php

namespace Wexample\SymfonyApi\DependencyInjection\Compiler;

use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Wexample\SymfonyApi\Api\Attribute\ApiCache;

/**
 * The entities some #[ApiCache] route is read from, gathered from the
 * controllers once, when the container is built: a flush writing anything
 * else — a page viewed, a token's last use — drops nothing
 * (ApiCacheDoctrineListener).
 */
class ApiCacheEntitiesCompilerPass implements CompilerPassInterface
{
    final public const string PARAMETER = 'api_cache_entities';

    public function process(ContainerBuilder $container): void
    {
        $entities = [];

        foreach (array_keys($container->findTaggedServiceIds('controller.service_arguments')) as $id) {
            $class = $container->getParameterBag()->resolveValue($container->getDefinition($id)->getClass() ?? $id);

            if (! is_string($class) || ! class_exists($class)) {
                continue;
            }

            foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                foreach ($method->getAttributes(ApiCache::class) as $attribute) {
                    foreach ($attribute->newInstance()->entities as $entity) {
                        $entities[ltrim($entity, '\\')] = true;
                    }
                }
            }
        }

        $container->setParameter(self::PARAMETER, array_keys($entities));
    }
}
