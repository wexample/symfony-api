<?php

namespace Wexample\SymfonyApi\Service;

use DateTimeImmutable;
use DateTimeZone;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Wexample\SymfonyApi\Enum\MachineSecurityEventType;
use Wexample\SymfonyApi\Event\MachineSecurityEvent;
use Wexample\SymfonyApi\Helper\ApiVersionHelper;
use Wexample\SymfonyApi\Interface\MachineClientInterface;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;

/**
 * Turns a machine token fact into a MachineSecurityEvent, with what the
 * request tells of it, and dispatches it.
 */
class MachineSecurityJournalService
{
    public const string REQUEST_ID_HEADER = 'X-Request-Id';

    public const string METHOD_ACCESS_TOKEN = 'access_token';

    private const string REQUEST_ID_ATTRIBUTE = '_wexample_api_request_id';

    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly RequestStack $requestStack,
        private readonly Security $security,
    ) {
    }

    /**
     * @param array<string, scalar|null> $extra
     */
    public function record(
        MachineSecurityEventType $type,
        ?MachineClientInterface $client = null,
        ?string $tokenHint = null,
        ?string $cause = null,
        array $extra = [],
    ): void {
        $request = $this->requestStack->getMainRequest();

        $this->eventDispatcher->dispatch(new MachineSecurityEvent(
            type: $type,
            occurredAt: new DateTimeImmutable('now', new DateTimeZone('UTC')),
            userId: $this->getClientId($client),
            cause: $cause,
            method: $request ? self::METHOD_ACCESS_TOKEN : null,
            firewall: $request ? $this->security->getFirewallConfig($request)?->getName() : null,
            ip: $request?->getClientIp(),
            userAgent: $request?->headers->get('User-Agent'),
            requestId: $this->getRequestId(),
            extra: [
                'token_hint' => $tokenHint,
                'api_version' => $request ? ApiVersionHelper::fromPath($request->getPathInfo()) : null,
            ] + $extra,
        ));
    }

    private function getClientId(?MachineClientInterface $client): ?string
    {
        if ($client instanceof AbstractEntity) {
            return (string) $client->getId();
        }

        return $client?->getUserIdentifier();
    }

    /**
     * The one a proxy gave, or one of our own, the same for every event of the
     * request.
     */
    private function getRequestId(): ?string
    {
        $request = $this->requestStack->getMainRequest();

        if (! $request) {
            return null;
        }

        if (! $request->attributes->has(self::REQUEST_ID_ATTRIBUTE)) {
            $request->attributes->set(
                self::REQUEST_ID_ATTRIBUTE,
                $request->headers->get(self::REQUEST_ID_HEADER) ?? bin2hex(random_bytes(8))
            );
        }

        return $request->attributes->get(self::REQUEST_ID_ATTRIBUTE);
    }
}
