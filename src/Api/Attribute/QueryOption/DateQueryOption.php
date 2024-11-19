<?php

namespace Wexample\SymfonyApi\Api\Attribute\QueryOption;

use Attribute;
use Symfony\Component\Validator\Constraint;
use Wexample\SymfonyHelpers\Helper\VariableHelper;
use Wexample\SymfonyHelpers\Validator\DateQueryStringConstraint;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class DateQueryOption extends AbstractQueryOption
{
    public function __construct(
        public string $key = VariableHelper::DATE,
        public mixed $default = null,
        bool $required = false,
    ) {
        parent::__construct(
            $required
        );
    }

    public function getConstraint(): Constraint
    {
        return new DateQueryStringConstraint();
    }
}
