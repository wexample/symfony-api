<?php

namespace Wexample\SymfonyApi\Exception;

use RuntimeException;

/**
 * Thrown by a batch processor refusing an item on a rule of its own: the item
 * is rolled back and reported `rejected` with this code, and its key stays
 * free — sent again, it is refused again. Any other exception is an `error`.
 */
class BatchItemRejectedException extends RuntimeException
{
    public function __construct(
        public readonly string $rejectionCode,
        string $message = '',
    ) {
        parent::__construct($message);
    }
}
