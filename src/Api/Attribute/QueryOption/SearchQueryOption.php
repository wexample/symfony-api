<?php

namespace Wexample\SymfonyApi\Api\Attribute\QueryOption;

use Attribute;
use Wexample\SymfonyHelpers\Helper\VariableHelper;

#[Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class SearchQueryOption extends MultipleTypesQueryOption
{
    public function __construct(
        public string $key = VariableHelper::SEARCH,
        public mixed $default = null,
        array $types = [],
        bool $required = false
    ) {
        parent::__construct(
            $this->key,
            $types,
            $this->default,
            $required
        );
    }
}
