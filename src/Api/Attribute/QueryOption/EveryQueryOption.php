<?php

namespace Wexample\SymfonyApi\Api\Attribute\QueryOption;

use Attribute;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\Trait\QueryOptionTrait;
use Wexample\SymfonyHelpers\Helper\VariableHelper;

#[Attribute(Attribute::TARGET_METHOD)]
class EveryQueryOption
{
    use QueryOptionTrait;

    public const KEY = '_' . VariableHelper::ALL;

    public string $key;

    public function __construct() {
        $this->key = self::KEY;
    }
}
