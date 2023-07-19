<?php

namespace Wexample\SymfonyApi\Api\Attribute\QueryOption;

use Attribute;
use Wexample\SymfonyHelpers\Helper\TypesHelper;

#[Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class BooleanQueryOption extends AbstractSimpleTypeQueryOption
{
    public function getSimpleTypeConstraint(): string
    {
        return TypesHelper::BOOLEAN;
    }
}