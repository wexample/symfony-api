<?php

namespace Wexample\SymfonyApi\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Wexample\SymfonyActivity\Class\ActivitySubject;
use Wexample\SymfonyActivity\Enum\ActivityCategory;
use Wexample\SymfonyActivity\Service\ActivityRecorder;
use Wexample\SymfonyApi\Event\MachineSecurityEvent;
use Wexample\SymfonyApi\Event\UserTokenSecurityEvent;
use Wexample\SymfonySecurity\Event\AbstractSecurityEvent;

/**
 * Every token fact about a known holder — a token issued, revoked, refused,
 * throttled —, written to its history in the `security` category of
 * symfony-activity: what a client does wrong with the api shows where its
 * sign-ins do. A refusal or a throttling is merged with its repeats of the
 * same token and cause (`merge_within`): a flood is one entry counting, not
 * a row per request.
 *
 * Registered only when the symfony-activity bundle is enabled, and recording
 * nothing until the application enables `security` there. A token no one
 * holds has no history to go into: the journal's log keeps it.
 */
class ApiTokenActivitySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ActivityRecorder $recorder,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            MachineSecurityEvent::class => 'onSecurityEvent',
            UserTokenSecurityEvent::class => 'onSecurityEvent',
        ];
    }

    public function onSecurityEvent(AbstractSecurityEvent $event): void
    {
        $clientType = $event->extra['client_type'] ?? null;

        if (null === $event->userId
            || ! is_string($clientType)
            || ! $this->recorder->records(ActivityCategory::SECURITY->value)) {
            return;
        }

        $tokenHint = $event->extra['token_hint'] ?? null;

        $this->recorder->record(
            new ActivitySubject($clientType, $event->userId),
            ActivityCategory::SECURITY->value,
            ActivityCategory::SECURITY->value . '.' . $event->type->value,
            array_filter([
                'cause' => $event->cause,
                'token_hint' => $tokenHint,
                'api_version' => $event->extra['api_version'] ?? null,
                'ip' => $event->ip,
                'user_agent' => $event->userAgent,
            ], static fn ($value) => null !== $value),
            fingerprint: $event->type->isFailure()
                ? hash('sha256', $event->type->value . '|' . $event->cause . '|' . $tokenHint)
                : null,
        );
    }
}
