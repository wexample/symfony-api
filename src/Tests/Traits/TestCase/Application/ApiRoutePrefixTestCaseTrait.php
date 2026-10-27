<?php

namespace Wexample\SymfonyApi\Tests\Traits\TestCase\Application;

use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Wexample\SymfonyApi\Api\Controller\AbstractApiController;
use Wexample\SymfonyApi\Helper\ApiHelper;

/**
 * Holds the two prefixes of the api routes, for a bundle or an application
 * to run in its own suite (`usage/route-prefixes`):
 *
 * - under `/_api/`, what the pages read on the session — a session firewall;
 * - under `/api/`, what programs call with their own credentials — a token
 *   firewall, or no access rule at all; never a session.
 *
 * A route of an AbstractApiController elsewhere, or on the wrong kind of
 * firewall, is reported.
 */
trait ApiRoutePrefixTestCaseTrait
{
    /**
     * @param list<string> $ignoredRouteNames
     */
    protected function assertApiRoutesFollowPrefixNorm(array $ignoredRouteNames = []): void
    {
        $problems = $this->findApiRoutePrefixProblems($ignoredRouteNames);

        $this->assertSame([], $problems, "Api routes off the prefix norm:\n" . implode("\n", $problems));
    }

    /**
     * @param list<string> $ignoredRouteNames
     * @return list<string>
     */
    protected function findApiRoutePrefixProblems(array $ignoredRouteNames = []): array
    {
        $container = static::getContainer();
        $firewallMap = $container->has('security.firewall.map') ? $container->get('security.firewall.map') : null;
        $accessMap = $container->has('security.access_map') ? $container->get('security.access_map') : null;
        $problems = [];

        foreach ($container->get('router')->getRouteCollection() as $name => $route) {
            $controller = explode('::', (string) $route->getDefault('_controller'))[0];

            // The package's own test endpoints, under /_test/, are routed in
            // the test environment of the applications that import them.
            if (in_array($name, $ignoredRouteNames, true)
                || ! is_a($controller, AbstractApiController::class, true)
                || str_starts_with($controller, 'Wexample\\SymfonyApi\\Api\\Controller\\Test\\')
            ) {
                continue;
            }

            $path = $route->getPath();
            $request = Request::create(preg_replace('/\{[^}]+}/', 'x', $path), ($route->getMethods() ?: ['GET'])[0]);
            $firewall = $firewallMap?->getFirewallConfig($request);
            $session = null !== $firewall && $firewall->isSecurityEnabled() && ! $firewall->isStateless();
            [$attributes] = $accessMap?->getPatterns($request) ?? [null];
            $public = (empty($attributes) || [AuthenticatedVoter::PUBLIC_ACCESS] === $attributes)
                && ! $this->isGrantedInController((string) $route->getDefault('_controller'));

            if (str_starts_with($path, ApiHelper::PATH_PREFIX_FRONT)) {
                if (! $session) {
                    $problems[] = sprintf('%s %s: read by the pages, it needs the session firewall, not %s.', $name, $path, $firewall?->getName() ?? 'none');
                }

                continue;
            }

            if (str_starts_with($path, ApiHelper::PATH_PREFIX_API)) {
                if ($session && ! $public) {
                    $problems[] = sprintf('%s %s: signed in by the session, it is read by the pages and belongs under %s.', $name, $path, ApiHelper::PATH_PREFIX_FRONT);
                }

                continue;
            }

            $problems[] = sprintf('%s %s: an api route lives under %s or %s.', $name, $path, ApiHelper::PATH_PREFIX_API, ApiHelper::PATH_PREFIX_FRONT);
        }

        return $problems;
    }

    /**
     * An access rule may be absent and the route still signed in: the
     * controller asks for a role itself.
     */
    private function isGrantedInController(string $controller): bool
    {
        [$class, $method] = explode('::', $controller) + [1 => null];

        if (! empty((new ReflectionClass($class))->getAttributes(IsGranted::class))) {
            return true;
        }

        return null !== $method
            && method_exists($class, $method)
            && ! empty((new ReflectionMethod($class, $method))->getAttributes(IsGranted::class));
    }
}
