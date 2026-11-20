<?php

namespace Wexample\SymfonyApi\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Wexample\SymfonyApi\Api\Attribute\ApiCache;
use Wexample\SymfonyApi\Security\AbstractApiTokenHandler;
use Wexample\SymfonyApi\Service\ApiCacheService;

/**
 * The answers of the routes marked #[ApiCache].
 *
 * Every successful read is told cacheable — `public` for a call carrying
 * no token, `private` for a token's —, with an ETag, a 304 for a caller
 * holding it. A call carrying no token is answered from the server's cache
 * when it holds the answer: same route, same query, same language; the
 * controller is not run. What happened is on the main request,
 * `_api_cache`: `hit` or `miss`, for whatever counts the calls.
 */
class ApiCacheSubscriber implements EventSubscriberInterface
{
    final public const string REQUEST_ATTRIBUTE_CACHE = '_api_cache';

    private const string REQUEST_ATTRIBUTE_CONFIG = '_wexample_api_cache_config';

    private const string REQUEST_ATTRIBUTE_KEY = '_wexample_api_cache_key';

    public function __construct(
        private readonly ApiCacheService $cache,
        private readonly RequestStack $requestStack,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => ['onController', -16],
            // After the answer is built, before the headers of the rate limit.
            KernelEvents::RESPONSE => ['onResponse', 8],
        ];
    }

    public function onController(ControllerEvent $event): void
    {
        $request = $event->getRequest();
        $config = $event->getAttributes(ApiCache::class)[0] ?? null;

        if (! $config instanceof ApiCache || ! in_array($request->getMethod(), [Request::METHOD_GET, Request::METHOD_HEAD], true)) {
            return;
        }

        $request->attributes->set(self::REQUEST_ATTRIBUTE_CONFIG, $config);

        if (! $this->isServerCached($request, $config)) {
            return;
        }

        $key = self::key($request);
        $request->attributes->set(self::REQUEST_ATTRIBUTE_KEY, $key);
        $item = $this->cache->getPool()->getItem($key);

        if (! $item->isHit()) {
            $this->mark('miss');

            return;
        }

        $this->mark('hit');
        $kept = $item->get();
        $event->setController(static fn (): Response => new Response($kept['content'], Response::HTTP_OK, ['Content-Type' => $kept['type']]));
    }

    public function onResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();
        $config = $request->attributes->get(self::REQUEST_ATTRIBUTE_CONFIG);

        if (! $config instanceof ApiCache || Response::HTTP_OK !== $response->getStatusCode()) {
            return;
        }

        $key = $request->attributes->get(self::REQUEST_ATTRIBUTE_KEY);

        if (null !== $key && 'miss' === $this->requestStack->getMainRequest()?->attributes->get(self::REQUEST_ATTRIBUTE_CACHE)) {
            $item = $this->cache->getPool()->getItem($key);
            $item->set(['content' => $response->getContent(), 'type' => $response->headers->get('Content-Type')]);
            $item->expiresAfter($config->maxAge);
            $item->tag(array_map(ApiCacheService::getEntityTag(...), $config->entities));
            $this->cache->getPool()->save($item);
        }

        $response->setCache([
            'max_age' => $config->maxAge,
            'public' => ! $this->hasToken($request),
            'private' => $this->hasToken($request),
            'etag' => hash('xxh128', (string) $response->getContent()),
        ]);
        $response->setVary(['Accept-Language', 'Authorization'], false);
        $response->isNotModified($request);
    }

    private function isServerCached(Request $request, ApiCache $config): bool
    {
        return $config->server && ! $this->hasToken($request) && ! $request->headers->has('Authorization');
    }

    private function hasToken(Request $request): bool
    {
        return $request->attributes->has(AbstractApiTokenHandler::REQUEST_ATTRIBUTE_TOKEN)
            || $request->headers->has('Authorization');
    }

    /**
     * Said on the main request: a tool's call is a sub-request of the MCP one.
     */
    private function mark(string $outcome): void
    {
        $this->requestStack->getMainRequest()?->attributes->set(self::REQUEST_ATTRIBUTE_CACHE, $outcome);
    }

    private static function key(Request $request): string
    {
        $query = $request->query->all();
        self::sortRecursive($query);

        return 'api_answer.' . hash('xxh128', implode("\0", [
            $request->attributes->get('_route'),
            json_encode($request->attributes->get('_route_params')),
            json_encode($query),
            $request->getLocale(),
        ]));
    }

    private static function sortRecursive(array &$values): void
    {
        ksort($values);

        foreach ($values as &$value) {
            if (is_array($value)) {
                self::sortRecursive($value);
            }
        }
    }
}
