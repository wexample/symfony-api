<?php

namespace Wexample\SymfonyApi\EventSubscriber;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Exception\AccountStatusException;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Wexample\SymfonyApi\Entity\AbstractApiToken;
use Wexample\SymfonyApi\Enum\MachineTokenRefusalCause;
use Wexample\SymfonyApi\Exception\MachineTokenRefusedException;
use Wexample\SymfonyApi\Exception\MachineTokenThrottledException;
use Wexample\SymfonyApi\Security\AbstractApiTokenHandler;
use Wexample\SymfonyApi\Service\MachineSecurityJournalService;

/**
 * What follows a token authentication attempt, after the settings of the
 * handler that read the token: the last use of a token once every check
 * passed — the firewall's user checker included —, the journal entry of a
 * refusal or a throttling otherwise, and the failure counted against the
 * address.
 */
class ApiTokenSecuritySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MachineSecurityJournalService $journal,
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
        $attributes = $event->getRequest()->attributes;
        $token = $attributes->get(AbstractApiTokenHandler::REQUEST_ATTRIBUTE_TOKEN);
        $handler = $attributes->get(AbstractApiTokenHandler::REQUEST_ATTRIBUTE_HANDLER);

        if (! $token instanceof AbstractApiToken || ! $handler instanceof AbstractApiTokenHandler) {
            return;
        }

        // One write per interval, not one per request.
        $now = new DateTimeImmutable();
        $lastUsed = $token->getDateLastUsed();

        if (null !== $lastUsed && $now->getTimestamp() - $lastUsed->getTimestamp() < $handler->getLastUsedInterval()) {
            return;
        }

        $token->setDateLastUsed($now);
        $this->entityManager->flush();
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $attributes = $event->getRequest()->attributes;
        $handler = $attributes->get(AbstractApiTokenHandler::REQUEST_ATTRIBUTE_HANDLER);

        if (! $handler instanceof AbstractApiTokenHandler) {
            return;
        }

        $request = $event->getRequest();
        $hint = $attributes->get(AbstractApiTokenHandler::REQUEST_ATTRIBUTE_HINT);
        $eventTypes = $handler->getEventTypeClass();
        $throttled = $this->findException($event->getException(), MachineTokenThrottledException::class);

        if ($throttled) {
            $this->journal->record(
                $eventTypes::THROTTLED,
                $throttled->client,
                $hint,
                $throttled->limit,
                ['retry_after' => $throttled->retryAfter->format(DATE_ATOM)]
            );

            return;
        }

        if ($handler->isRateLimitEnabled()) {
            $handler->getIpFailuresLimiter()->create($request->getClientIp())->consume();
        }

        $cause = MachineTokenRefusalCause::UNKNOWN;
        $client = null;

        for ($exception = $event->getException(); null !== $exception; $exception = $exception->getPrevious()) {
            if ($exception instanceof MachineTokenRefusedException) {
                $cause = $exception->refusalCause;
                $client = $exception->client;

                break;
            }

            // Thrown by the firewall's user checker: the holder is switched off.
            if ($exception instanceof AccountStatusException) {
                $cause = MachineTokenRefusalCause::CLIENT_DISABLED;
                $client = $exception->getUser();

                break;
            }
        }

        $this->journal->record(
            $eventTypes::REFUSED,
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
