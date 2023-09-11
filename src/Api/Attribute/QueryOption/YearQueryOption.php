<?php

namespace Wexample\SymfonyApi\Api\Attribute\QueryOption;

use Attribute;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\Validator\Constraint;
use Wexample\SymfonyHelpers\Helper\DateHelper;
use Wexample\SymfonyHelpers\Helper\VariableHelper;

#[Attribute(Attribute::TARGET_METHOD)]
class YearQueryOption extends AbstractQueryOption
{
    public string $key = VariableHelper::YEAR;

    public function __construct(
        public mixed $default = null,
        bool $required = false,
    ) {
        $this->default = !is_null($this->default) ? $this->default : DateHelper::getCurrentYearInt();

        parent::__construct(
            $required
        );
    }

    public function getConstraint(): Constraint
    {
        return $this->buildConstraintType(Types::INTEGER);
    }
}
