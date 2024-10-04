<?php

namespace Wexample\SymfonyApi\Api\Attribute\QueryOption;

use Attribute;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Type;
use Wexample\SymfonyHelpers\Helper\VariableHelper;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class PageQueryOption extends AbstractQueryOption
{
    public string $key = VariableHelper::PAGE;

    public function __construct(
        public mixed $default = 0,
        bool $required = false,
    ) {
        parent::__construct(
            $required
        );
    }

    public function getConstraint(): Constraint
    {
        return new Type(Types::INTEGER);
    }
}
