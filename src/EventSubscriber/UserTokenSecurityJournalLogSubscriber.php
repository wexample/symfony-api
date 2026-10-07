<?php

namespace Wexample\SymfonyApi\EventSubscriber;

use Monolog\Attribute\WithMonologChannel;
use Wexample\SymfonyApi\Event\UserTokenSecurityEvent;
use Wexample\SymfonySecurity\EventSubscriber\AbstractSecurityEventLogSubscriber;

/**
 * The default recorder of user token facts: a log channel of their own,
 * `user_token_security`.
 */
#[WithMonologChannel('user_token_security')]
class UserTokenSecurityJournalLogSubscriber extends AbstractSecurityEventLogSubscriber
{
    public static function getSubscribedEvents(): array
    {
        return [UserTokenSecurityEvent::class => 'onSecurityEvent'];
    }
}
