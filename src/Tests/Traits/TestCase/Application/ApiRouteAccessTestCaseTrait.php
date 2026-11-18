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
        $action = null !== $method && method_exists($class, $method) ? new ReflectionMethod($class, $method) : null;

        // #[IsGranted] counts where Symfony reads it: the controller's class
        // and its action, not a parent class.
        if ([] !== (new ReflectionClass($class))->getAttributes(IsGranted::class)
            || [] !== ($action?->getAttributes(IsGranted::class) ?? [])
            || [] !== ($action?->getAttributes(ApiAccess::class) ?? [])
        ) {
            return true;
        }

        // #[ApiAccess] says what the code does: a parent class saying it for
        // every controller built on it holds.
        for ($reflection = new ReflectionClass($class); $reflection; $reflection = $reflection->getParentClass()) {
            if ([] !== $reflection->getAttributes(ApiAccess::class)) {
                return true;
            }
        }

        return false;
    }
}
