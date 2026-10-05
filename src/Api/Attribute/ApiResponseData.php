<?php

namespace Wexample\SymfonyApi\Api\Attribute;

use Attribute;

/**
 * What the `data` of a route's success response holds, for its
 * documentation: the controller returns an ApiResponse, whose content no
 * generator can see.
 *
 * `$class` is described from its properties, as a body DTO is.
 * `collection` wraps it in `items`, as apiResponseCollection() does;
 * `paginated` adds the `pagination` of apiResponsePaginated().
 */
#[Attribute(Attribute::TARGET_METHOD)]
class ApiResponseData
{
    public function __construct(
        public readonly string $class,
        public readonly bool $collection = false,
        public readonly bool $paginated = false,
    ) {
    }
}
