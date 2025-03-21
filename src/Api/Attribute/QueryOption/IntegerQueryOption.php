<?php

namespace Wexample\SymfonyApi\Api\Attribute\QueryOption;

use Attribute;
use Wexample\SymfonyHelpers\Helper\TypesHelper;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class IntegerQueryOption extends AbstractSimpleTypeQueryOption
{
    public function getSimpleTypeConstraint(): string
    {
        return TypesHelper::INTEGER;
    }
}
