<?php

namespace Wexample\SymfonyApi\Api\Attribute\QueryOption;

use Wexample\SymfonyApi\Api\Attribute\QueryOption\Trait\QueryOptionConstrainedTrait;

abstract class AbstractQueryOption
{
    use QueryOptionConstrainedTrait;

    public function __construct(
        bool $required = false
    ) {
        $this->required = $required;
    }
}
