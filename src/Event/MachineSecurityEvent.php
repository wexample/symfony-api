<?php

namespace Wexample\SymfonyApi\Event;

use Wexample\SymfonyApi\Enum\MachineSecurityEventType;
use Wexample\SymfonySecurity\Event\AbstractSecurityEvent;

/**
 * A machine token fact, dispatched by MachineSecurityJournalService for
 * whoever records it: the package's own log subscriber, an audit package.
 * Its fields are those of AbstractSecurityEvent: `user_id` holds the machine
 * client. It never holds a secret or a hash — a token is named by its hint,
 * in `extra.token_hint`.
 *
 * @property-read MachineSecurityEventType $type
 */
class MachineSecurityEvent extends AbstractSecurityEvent
{
    public function __construct(MachineSecurityEventType $type, mixed ...$arguments)
    {
        parent::__construct($type, ...$arguments);
    }
}
