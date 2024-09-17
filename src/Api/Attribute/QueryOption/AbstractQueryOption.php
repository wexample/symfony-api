<?php

namespace Wexample\SymfonyApi\Api\Attribute\QueryOption;

use Symfony\Component\Validator\Constraints\Type;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\Trait\QueryOptionConstrainedTrait;
use Wexample\SymfonyHelpers\Helper\RequestHelper;

abstract class AbstractQueryOption
{
    use QueryOptionConstrainedTrait;

    public function __construct(
        bool $required = false
    ) {
        $this->required = $required;
    }

    public function parseValue(mixed $value): mixed
    {
        $constraint = $this->getConstraint();

        // Parse value if constraint is on type.
        if ($constraint instanceof Type) {
            return RequestHelper::parseRequestValue(
                $value,
                $constraint->type
            );

        }

        return $value;
    }
}
