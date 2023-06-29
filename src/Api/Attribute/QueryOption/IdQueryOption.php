<?php

namespace Wexample\SymfonyApi\Api\Attribute\QueryOption;

use Attribute;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\Validator\Constraint;
use Wexample\SymfonyHelpers\Helper\VariableHelper;

#[\Attribute(\Attribute::TARGET_METHOD)]
class IdQueryOption extends AbstractQueryOption
{
    public function __construct(
        public string $key = VariableHelper::ID,
        public mixed $default = null,
        bool $required = false,
    ) {
        parent::__construct(
            $required
        );
    }

    public function getConstraint(): Constraint
    {
        return $this->buildConstraintType(Types::INTEGER);
    }
}
