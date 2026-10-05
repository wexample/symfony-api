<?php

namespace Wexample\SymfonyApi\EventSubscriber;

use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Wexample\SymfonyApi\Helper\ApiVersionHelper;

/**
 * Tells the callers of a deprecated API version so, on every response —
 * errors and refusals included: `Deprecation` (RFC 9745), `Sunset`
 * (RFC 8594) and the links to read about it.
 */
class ApiVersionDeprecationSubscriber implements EventSubscriberInterface
{
    /**
     * @param array<string, array{deprecation: ?string, sunset: ?string, link: ?string}> $versions
     */
    public function __construct(
        #[Autowire(param: 'api_versions')]
        private readonly array $versions,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => 'onKernelResponse'];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        $version = ApiVersionHelper::fromPath($event->getRequest()->getPathInfo());
        $config = null !== $version ? ($this->versions[$version] ?? null) : null;

        if (null === $config) {
            return;
        }

        $headers = $event->getResponse()->headers;

        if (null !== $config['deprecation']) {
            $headers->set('Deprecation', '@' . $this->parseDate($config['deprecation'])->getTimestamp());
        }

        if (null !== $config['sunset']) {
            // The HTTP-date of RFC 9110, spelled out: DATE_RFC7231 is deprecated since
            // PHP 8.5, and parseDate() already puts the date in UTC.
            $headers->set('Sunset', $this->parseDate($config['sunset'])->format('D, d M Y H:i:s \G\M\T'));
        }

        if (null !== $config['link']) {
            $headers->set('Link', '<' . $config['link'] . '>; rel="deprecation"; type="text/html"', false);
        }
    }

    private function parseDate(string $date): DateTimeImmutable
    {
        return (new DateTimeImmutable($date, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('UTC'));
    }
}
