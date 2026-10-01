<?php

namespace Wexample\SymfonyApi\EventSubscriber;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Core\Exception\AccountStatusException;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Wexample\SymfonyApi\DependencyInjection\WexampleSymfonyApiExtension;
use Wexample\SymfonyApi\Entity\AbstractMachineToken;
use Wexample\SymfonyApi\Enum\MachineSecurityEventType;
use Wexample\SymfonyApi\Enum\MachineTokenRefusalCause;
use Wexample\SymfonyApi\Exception\MachineTokenRefusedException;
use Wexample\SymfonyApi\Exception\MachineTokenThrottledException;
use Wexample\SymfonyApi\Interface\MachineClientInterface;
use Wexample\SymfonyApi\Security\MachineTokenHandler;
use Wexample\SymfonyApi\Service\MachineSecurityJournalService;

/**
 * What follows a machine authentication attempt: the last use of a token once
 * every check passed — the firewall's user checker included —, the journal
 * entry of a refusal or a throttling otherwise, and the failure counted
 * against the address.
 */
class MachineTokenSecuritySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MachineSecurityJournalService $journal,
        #[Autowire(param: 'api_machine_token_last_used_interval')]
        private readonly int $lastUsedInterval,
        #[Autowire(param: 'api_machine_token_rate_limit_enabled')]
        private readonly bool $rateLimitEnabled,
        #[Autowire(service: 'limiter.' . WexampleSymfonyApiExtension::LIMITER_MACHINE_IP_FAILURES)]
        private readonly RateLimiterFactoryInterface $ipFailuresLimiter,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
            LoginFailureEvent::class => 'onLoginFailure',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $token = $event->getRequest()->attributes->get(MachineTokenHandler::REQUEST_ATTRIBUTE_TOKEN);

        if (! $token instanceof AbstractMachineToken) {
            return;
        }

        // One write per interval, not one per request.
        $now = new DateTimeImmutable();
        $lastUsed = $token->getDateLastUsed();

        if (null !== $lastUsed && $now->getTimestamp() - $lastUsed->getTimestamp() < $this->lastUsedInterval) {
            return;
        }

        $token->setDateLastUsed($now);
        $this->entityManager->flush();
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $attributes = $event->getRequest()->attributes;

        if (! $attributes->has(MachineTokenHandler::REQUEST_ATTRIBUTE_HINT)) {
            return;
        }

        $request = $event->getRequest();
        $hint = $attributes->get(MachineTokenHandler::REQUEST_ATTRIBUTE_HINT);
        $throttled = $this->findException($event->getException(), MachineTokenThrottledException::class);

        if ($throttled) {
            $this->journal->record(
                MachineSecurityEventType::THROTTLED,
                $throttled->client,
                $hint,
                $throttled->limit,
                ['retry_after' => $throttled->retryAfter->format(DATE_ATOM)]
            );

            return;
        }

        if ($this->rateLimitEnabled) {
            $this->ipFailuresLimiter->create($request->getClientIp())->consume();
        }

        $cause = MachineTokenRefusalCause::UNKNOWN;
        $client = null;

        for ($exception = $event->getException(); null !== $exception; $exception = $exception->getPrevious()) {
            if ($exception instanceof MachineTokenRefusedException) {
                $cause = $exception->refusalCause;
                $client = $exception->client;

                break;
            }

            // Thrown by the firewall's user checker: the client is switched off.
            if ($exception instanceof AccountStatusException) {
                $cause = MachineTokenRefusalCause::CLIENT_DISABLED;
                $user = $exception->getUser();
                $client = $user instanceof MachineClientInterface ? $user : null;

                break;
            }
        }

        $this->journal->record(
            MachineSecurityEventType::REFUSED,
            $client,
            $hint,
            $cause->value
        );
    }

    /**
     * @template T of \Throwable
     * @param class-string<T> $class
     * @return T|null
     */
    private function findException(?\Throwable $exception, string $class): ?\Throwable
    {
        for (; null !== $exception; $exception = $exception->getPrevious()) {
            if ($exception instanceof $class) {
                return $exception;
            }
        }

        return null;
    }
}
