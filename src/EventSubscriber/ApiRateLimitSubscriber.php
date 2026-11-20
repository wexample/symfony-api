<?php

namespace Wexample\SymfonyApi\EventSubscriber;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Wexample\SymfonyApi\Api\Controller\AbstractApiController;
use Wexample\SymfonyApi\DependencyInjection\WexampleSymfonyApiExtension;
use Wexample\SymfonyApi\Security\AbstractApiTokenAuthenticationFailureHandler;
use Wexample\SymfonyApi\Security\AbstractApiTokenHandler;

/**
 * Limits the calls carrying no token under `anonymous_rate_limit.paths`, per
 * address (`anonymous_rate_limit.windows`): past a window, a 429 with its
 * Retry-After, in the api's envelope, as a throttled token gets. A token's
 * calls are limited by its handler, its kind's limit or its holder's plan.
 *
 * Every answer counted against a limit, a token's or an address's, says
 * where the caller stands: X-RateLimit-Limit, X-RateLimit-Remaining,
 * X-RateLimit-Reset (seconds until a call is allowed again).
 */
class ApiRateLimitSubscriber implements EventSubscriberInterface
{
    /**
     * @param list<string> $paths
     * @param list<string> $excludedRoutes
     */
    public function __construct(
        #[Autowire(service: 'limiter.' . WexampleSymfonyApiExtension::LIMITER_ANONYMOUS)]
        private readonly RateLimiterFactoryInterface $anonymousLimiter,
        #[Autowire(param: 'api_anonymous_rate_limit_enabled')]
        private readonly bool $enabled,
        #[Autowire(param: 'api_anonymous_rate_limit_paths')]
        private readonly array $paths,
        #[Autowire(param: 'api_anonymous_rate_limit_excluded_routes')]
        private readonly array $excludedRoutes,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Right after the firewall (8): a token is known by then, and a
            // call it refused never gets here.
            KernelEvents::REQUEST => ['onRequest', 7],
            KernelEvents::RESPONSE => 'onResponse',
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (! $this->enabled
            || ! $event->isMainRequest()
            || $request->attributes->has(AbstractApiTokenHandler::REQUEST_ATTRIBUTE_TOKEN)
            || in_array($request->attributes->get('_route'), $this->excludedRoutes, true)
            || ! $this->isLimitedPath($request->getPathInfo())) {
            return;
        }

        $limit = $this->anonymousLimiter->create((string) $request->getClientIp())->consume();
        $request->attributes->set(AbstractApiTokenHandler::REQUEST_ATTRIBUTE_RATE_LIMIT, $limit);

        if (! $limit->isAccepted()) {
            $response = AbstractApiController::apiResponseError(
                message: AbstractApiTokenAuthenticationFailureHandler::MESSAGE_THROTTLED,
                code: Response::HTTP_TOO_MANY_REQUESTS
            )->toJsonResponse();
            $response->headers->set('Retry-After', (string) self::secondsUntil($limit));

            $event->setResponse($response);
        }
    }

    public function onResponse(ResponseEvent $event): void
    {
        $limit = $event->getRequest()->attributes->get(AbstractApiTokenHandler::REQUEST_ATTRIBUTE_RATE_LIMIT);

        if (! $event->isMainRequest() || ! $limit instanceof RateLimit) {
            return;
        }

        $event->getResponse()->headers->add([
            'X-RateLimit-Limit' => (string) $limit->getLimit(),
            'X-RateLimit-Remaining' => (string) $limit->getRemainingTokens(),
            'X-RateLimit-Reset' => (string) self::secondsUntil($limit),
        ]);
    }

    private function isLimitedPath(string $path): bool
    {
        foreach ($this->paths as $pattern) {
            if (1 === preg_match('#' . $pattern . '#', $path)) {
                return true;
            }
        }

        return false;
    }

    private static function secondsUntil(RateLimit $limit): int
    {
        return max(0, $limit->getRetryAfter()->getTimestamp() - time());
    }
}
