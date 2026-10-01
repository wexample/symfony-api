<?php

namespace Wexample\SymfonyApi\Enum;

/**
 * What became of one item of a batch, and what its sender should do with it.
 */
enum BatchItemOutcome: string
{
    // Stored: drop it from the buffer.
    case ACCEPTED = 'accepted';

    // Already stored by an earlier send: drop it.
    case DUPLICATE = 'duplicate';

    // Invalid: it will never pass as it is.
    case REJECTED = 'rejected';

    // Its key was already used for another payload: drop it, and look into it.
    case CONFLICT = 'conflict';

    // The server failed on it: keep it, and send it again later.
    case ERROR = 'error';
}
