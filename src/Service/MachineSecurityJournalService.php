<?php

namespace Wexample\SymfonyApi\Service;

use DateTimeImmutable;
use DateTimeZone;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Wexample\SymfonyApi\Enum\MachineSecurityEventType;
use Wexample\SymfonyApi\Enum\UserTokenSecurityEventType;
use Wexample\SymfonyApi\Event\MachineSecurityEvent;
use Wexample\SymfonyApi\Event\UserTokenSecurityEvent;
use Wexample\SymfonyApi\Helper\ApiVersionHelper;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonySecurity\Helper\RequestIdHelper;

/**
 * Turns a token fact into its event — a MachineSecurityEvent or a
 * UserTokenSecurityEvent, after the type — with what the request tells of it,
 * and dispatches it.
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
        MachineSecurityEventType|UserTokenSecurityEventType $type,
        ?UserInterface $client = null,
        ?string $tokenHint = null,
        ?string $cause = null,
        array $extra = [],
    ): void {
        $request = $this->requestStack->getMainRequest();

        $eventClass = $type instanceof UserTokenSecurityEventType
            ? UserTokenSecurityEvent::class
            : MachineSecurityEvent::class;

        $this->eventDispatcher->dispatch(new $eventClass(
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

    private function getClientId(?UserInterface $client): ?string
    {
        if ($client instanceof AbstractEntity) {
            return (string) $client->getId();
        }

        return $client?->getUserIdentifier();
    }
}
