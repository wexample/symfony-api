<?php

namespace Wexample\SymfonyApi\Attribute;

use Attribute;

/**
 * Marks an entity exposed by the API.
 *
 * Filestate scaffolds its default normalizer and its controller.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class ApiEntity
{
}
