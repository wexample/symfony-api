<?php

namespace Wexample\SymfonyApi\Event;

use Wexample\SymfonyApi\Enum\UserTokenSecurityEventType;
use Wexample\SymfonySecurity\Event\AbstractSecurityEvent;

/**
 * A user token fact, dispatched by MachineSecurityJournalService like a
 * machine one. `user_id` holds the person; a token is named by its hint, in
 * `extra.token_hint`.
 *
 * @property-read UserTokenSecurityEventType $type
 */
class UserTokenSecurityEvent extends AbstractSecurityEvent
{
    public function __construct(UserTokenSecurityEventType $type, mixed ...$arguments)
    {
        parent::__construct($type, ...$arguments);
    }
}
