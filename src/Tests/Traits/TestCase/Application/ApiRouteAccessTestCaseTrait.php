<?php

namespace Wexample\SymfonyApi\Tests\Traits\TestCase\Application;

use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Wexample\SymfonyApi\Api\Attribute\ApiAccess;
use Wexample\SymfonyApi\Api\Controller\AbstractApiController;

/**
 * Every api route says who reaches it — an access rule matching its path, an
 * `#[IsGranted]`, or an `#[ApiAccess]` —, for a bundle or an application to
 * run in its own suite (`usage/route-access`). A route saying nothing is
 * reported: an access forgotten, or a rule written for a path the route has
 * left.
 */
trait ApiRouteAccessTestCaseTrait
{
    /**
     * @param list<string> $ignoredRouteNames
     */
    protected function assertApiRoutesDeclareTheirAccess(array $ignoredRouteNames = []): void
    {
        $problems = $this->findApiRoutesWithoutAccess($ignoredRouteNames);

        $this->assertSame([], $problems, "Api routes saying nothing of who reaches them:\n" . implode("\n", $problems));
    }

    /**
     * @param list<string> $ignoredRouteNames
     * @return list<string>
     */
    protected function findApiRoutesWithoutAccess(array $ignoredRouteNames = []): array
    {
        $container = static::getContainer();
        $accessMap = $container->has('security.access_map') ? $container->get('security.access_map') : null;
        $problems = [];

        foreach ($container->get('router')->getRouteCollection() as $name => $route) {
            [$class, $method] = explode('::', (string) $route->getDefault('_controller')) + [1 => null];

            if (in_array($name, $ignoredRouteNames, true)
                || ! is_a($class, AbstractApiController::class, true)
                // The package's own test endpoints, routed in test only.
                || str_starts_with($class, 'Wexample\\SymfonyApi\\Api\\Controller\\Test\\')
            ) {
                continue;
            }

            $request = Request::create(preg_replace('/\{[^}]+}/', 'x', $route->getPath()), ($route->getMethods() ?: ['GET'])[0]);
            [$attributes] = $accessMap?->getPatterns($request) ?? [null];

            if (! empty($attributes) || $this->declaresAccess($class, $method)) {
                continue;
            }

            $problems[] = sprintf('%s %s (%s): no access rule, no #[IsGranted], no #[ApiAccess].', $name, $route->getPath(), $route->getDefault('_controller'));
        }

        return $problems;
    }

    private function declaresAccess(string $class, ?string $method): bool
    {
        $reflections = [new ReflectionClass($class)];

        if (null !== $method && method_exists($class, $method)) {
            $reflections[] = new ReflectionMethod($class, $method);
        }

        foreach ($reflections as $reflection) {
            if ([] !== $reflection->getAttributes(IsGranted::class) || [] !== $reflection->getAttributes(ApiAccess::class)) {
                return true;
            }
        }

        return false;
    }
}
