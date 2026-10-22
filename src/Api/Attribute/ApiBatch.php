<?php

namespace Wexample\SymfonyApi\Api\Attribute;

use Attribute;

/**
 * Declares a controller method as a batch receiver of `$itemDto` items, for
 * its documentation: BatchReceiverService is called inside the method, where
 * no generator can see it.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class ApiBatch
{
    public function __construct(
        public readonly string $itemDto,
        public readonly ?int $maxItems = null,
    ) {
    }
}
