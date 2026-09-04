<?php

namespace Wexample\SymfonyApi\Attribute;

use Attribute;

/**
 * Marks an entity reachable through a default API controller.
 *
 * Filestate scaffolds that controller. A version places it in the matching
 * versioned directory, which has to exist beforehand — creating an API
 * version is a decision, not a side effect of annotating an entity.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class ApiEndpoint
{
    public function __construct(
        public readonly ?string $version = null
    ) {
    }
}
