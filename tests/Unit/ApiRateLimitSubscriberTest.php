<?php

namespace Wexample\SymfonyApi\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Wexample\SymfonyApi\EventSubscriber\ApiRateLimitSubscriber;
use Wexample\SymfonyApi\Security\AbstractApiTokenHandler;

class ApiRateLimitSubscriberTest extends TestCase
{
    private ApiRateLimitSubscriber $subscriber;

    private HttpKernelInterface $kernel;

    protected function setUp(): void
    {
        $this->subscriber = new ApiRateLimitSubscriber(
            new RateLimiterFactory(['id' => 'test', 'policy' => 'sliding_window', 'limit' => 2, 'interval' => '1 minute'], new InMemoryStorage()),
            true,
            ['^/api/'],
            ['api_health'],
        );
        $this->kernel = $this->createStub(HttpKernelInterface::class);
    }

    public function testAnAnonymousCallerIsStoppedPastItsWindow(): void
    {
        $this->assertSame(Response::HTTP_OK, $this->call('/api/people')->getStatusCode());
        $this->assertSame(Response::HTTP_OK, $this->call('/api/people')->getStatusCode());

        $response = $this->call('/api/people');

        $this->assertSame(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());
        $this->assertGreaterThan(0, (int) $response->headers->get('Retry-After'));
        $this->assertSame('0', $response->headers->get('X-RateLimit-Remaining'));
    }

    public function testEveryCountedAnswerSaysWhereTheCallerStands(): void
    {
        $response = $this->call('/api/people');

        $this->assertSame('2', $response->headers->get('X-RateLimit-Limit'));
        $this->assertSame('1', $response->headers->get('X-RateLimit-Remaining'));
    }

    public function testWhatIsNotAnAnonymousApiCallIsLeftAlone(): void
    {
        foreach (range(1, 3) as $ignored) {
            $this->assertSame(Response::HTTP_OK, $this->call('/api/health', 'api_health')->getStatusCode());
            $this->assertSame(Response::HTTP_OK, $this->call('/fr/')->getStatusCode());
            $this->assertSame(Response::HTTP_OK, $this->call('/api/people', null, true)->getStatusCode());
        }

        $this->assertNull($this->call('/fr/')->headers->get('X-RateLimit-Limit'));
    }

    private function call(string $path, ?string $route = null, bool $token = false): Response
    {
        $request = Request::create($path, server: ['REMOTE_ADDR' => '203.0.113.5']);
        $request->attributes->set('_route', $route);

        if ($token) {
            $request->attributes->set(AbstractApiTokenHandler::REQUEST_ATTRIBUTE_TOKEN, true);
        }

        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $this->subscriber->onRequest($event);
        $response = $event->getResponse() ?? new Response();
        $this->subscriber->onResponse(new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response));

        return $response;
    }
}
