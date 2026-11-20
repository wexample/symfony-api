<?php

namespace Wexample\SymfonyApi\Tests\Unit;

use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Wexample\SymfonyApi\Api\Attribute\ApiCache;
use Wexample\SymfonyApi\EventSubscriber\ApiCacheSubscriber;
use Wexample\SymfonyApi\Service\ApiCacheService;

class ApiCacheSubscriberTest extends TestCase
{
    private ApiCacheService $cache;

    private ApiCacheSubscriber $subscriber;

    private RequestStack $requestStack;

    private int $computed = 0;

    protected function setUp(): void
    {
        $this->cache = new ApiCacheService(new TagAwareAdapter(new ArrayAdapter()));
        $this->requestStack = new RequestStack();
        $this->subscriber = new ApiCacheSubscriber($this->cache, $this->requestStack);
    }

    public function testAnAnonymousCallIsAnsweredFromTheServerOnceKept(): void
    {
        [$first, $firstRequest] = $this->call();
        [$second, $secondRequest] = $this->call();

        $this->assertSame(1, $this->computed);
        $this->assertSame('miss', $firstRequest->attributes->get(ApiCacheSubscriber::REQUEST_ATTRIBUTE_CACHE));
        $this->assertSame('hit', $secondRequest->attributes->get(ApiCacheSubscriber::REQUEST_ATTRIBUTE_CACHE));
        $this->assertSame($first->getContent(), $second->getContent());
        $this->assertStringContainsString('public', (string) $second->headers->get('Cache-Control'));
        $this->assertNotNull($second->getEtag());
    }

    public function testWritingAnEntityDropsTheAnswersReadFromIt(): void
    {
        $this->call();
        $this->cache->invalidateEntities([stdClass::class]);
        $this->call();

        $this->assertSame(2, $this->computed);
    }

    public function testATokensCallIsNeverKeptOnTheServer(): void
    {
        [$response] = $this->call(token: true);
        $this->call(token: true);

        $this->assertSame(2, $this->computed);
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
    }

    public function testACallerHoldingTheAnswerIsToldItIsUnchanged(): void
    {
        [$response] = $this->call();
        [$again] = $this->call(etag: $response->getEtag());

        $this->assertSame(Response::HTTP_NOT_MODIFIED, $again->getStatusCode());
    }

    /**
     * The controller under test: counts the times it is run.
     */
    #[ApiCache(maxAge: 60, entities: [stdClass::class])]
    public function things(): Response
    {
        ++$this->computed;

        return new Response('{"things":' . $this->computed . '}', Response::HTTP_OK, ['Content-Type' => 'application/json']);
    }

    /**
     * @return array{0: Response, 1: Request}
     */
    private function call(bool $token = false, ?string $etag = null): array
    {
        $request = Request::create('/api/things', server: array_filter([
            'HTTP_AUTHORIZATION' => $token ? 'Bearer secret' : null,
            'HTTP_IF_NONE_MATCH' => $etag,
        ]));
        $request->attributes->set('_route', 'api_things');
        $request->attributes->set('_route_params', []);
        $this->requestStack->push($request);

        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new ControllerEvent($kernel, $this->things(...), $request, HttpKernelInterface::MAIN_REQUEST);
        $this->subscriber->onController($event);

        $response = ($event->getController())();
        $this->subscriber->onResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response));
        $this->requestStack->pop();

        return [$response, $request];
    }
}
