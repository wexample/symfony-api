<?php

namespace Wexample\SymfonyApi\Api\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class ValidateRequestContent
{
    public function __construct(
        public string $dto,
        public string $attributeName = 'content',
    ) {

    }
}
