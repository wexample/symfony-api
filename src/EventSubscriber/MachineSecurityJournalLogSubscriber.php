<?php

namespace Wexample\SymfonyApi\EventSubscriber;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Wexample\SymfonyApi\Event\MachineSecurityEvent;

/**
 * The default recorder of machine token facts: a log channel of their own,
 * `machine_security`, so that their retention — they hold IP addresses — is
 * set apart from the technical logs.
 */
#[WithMonologChannel('machine_security')]
class MachineSecurityJournalLogSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [MachineSecurityEvent::class => 'onMachineSecurityEvent'];
    }

    public function onMachineSecurityEvent(MachineSecurityEvent $event): void
    {
        $this->logger->log(
            $event->type->isFailure() ? 'warning' : 'info',
            $event->type->value,
            $event->toArray()
        );
    }
}
