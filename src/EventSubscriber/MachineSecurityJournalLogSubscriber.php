<?php

namespace Wexample\SymfonyApi\EventSubscriber;

use Monolog\Attribute\WithMonologChannel;
use Wexample\SymfonyApi\Event\MachineSecurityEvent;
use Wexample\SymfonySecurity\EventSubscriber\AbstractSecurityEventLogSubscriber;

/**
 * The default recorder of machine token facts: a log channel of their own,
 * `machine_security`.
 */
#[WithMonologChannel('machine_security')]
class MachineSecurityJournalLogSubscriber extends AbstractSecurityEventLogSubscriber
{
    public static function getSubscribedEvents(): array
    {
        return [MachineSecurityEvent::class => 'onSecurityEvent'];
    }
}
