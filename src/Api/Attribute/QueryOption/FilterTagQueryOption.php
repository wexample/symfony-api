<?php

namespace Wexample\SymfonyApi\Api\Attribute\QueryOption;

use Attribute;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Type;
use Wexample\SymfonyApi\Helper\ApiHelper;

#[Attribute(Attribute::TARGET_METHOD)]
class FilterTagQueryOption extends AbstractQueryOption
{
    public string $key = ApiHelper::_KEBAB_FILTER_TAG;

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
        return new Type(Types::STRING);
    }
}
