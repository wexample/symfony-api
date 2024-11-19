<?php

namespace Wexample\SymfonyApi\Api\Attribute\QueryOption;

use Attribute;
use Symfony\Component\Validator\Constraint;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class CustomQueryOption extends AbstractQueryOption
{
    public function __construct(
        public string $key,
        public Constraint $constraint,
        public mixed $default = null,
        bool $required = false,
    ) {
        parent::__construct(
            $required
        );
    }

    public function getConstraint(): Constraint
    {
        return $this->constraint;
    }
}
