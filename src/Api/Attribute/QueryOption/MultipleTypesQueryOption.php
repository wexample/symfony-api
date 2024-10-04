<?php

namespace Wexample\SymfonyApi\Api\Attribute\QueryOption;

use Attribute;
use Symfony\Component\Validator\Constraint;
use Wexample\SymfonyHelpers\Validator\MultipleTypeConstraint;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class MultipleTypesQueryOption extends AbstractQueryOption
{
    public function __construct(
        public string $key,
        readonly private array $types,
        public mixed $default = null,
        bool $required = false
    ) {
        parent::__construct($required);
    }

    public function getConstraint(): Constraint
    {
        return new MultipleTypeConstraint(
            $this->types
        );
    }
}
