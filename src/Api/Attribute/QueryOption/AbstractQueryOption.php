<?php

namespace Wexample\SymfonyApi\Api\Attribute\QueryOption;

use Wexample\SymfonyApi\Api\Attribute\QueryOption\Trait\QueryOptionTrait;

abstract class AbstractQueryOption
{
    use QueryOptionTrait;

    public function __construct(
        public bool $required = false
    ) {
    }
}
