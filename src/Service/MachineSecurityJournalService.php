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
use Wexample\SymfonySecurity\Helper\RequestIdHelper;

/**
 * Turns a machine token fact into a MachineSecurityEvent, with what the
 * request tells of it, and dispatches it.
 */
class MachineSecurityJournalService
{
    public const string REQUEST_ID_HEADER = RequestIdHelper::HEADER;

    public const string METHOD_ACCESS_TOKEN = 'access_token';

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
            requestId: RequestIdHelper::resolve($request),
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
}
