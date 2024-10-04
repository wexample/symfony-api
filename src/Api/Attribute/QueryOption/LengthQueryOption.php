<?php

namespace Wexample\SymfonyApi\Api\Attribute\QueryOption;

use Attribute;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Type;
use Wexample\SymfonyHelpers\Helper\VariableHelper;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class LengthQueryOption extends AbstractQueryOption
{
    final public const DEFAULT_PAGE_LENGTH = 10;

    public string $key = VariableHelper::LENGTH;

    public function __construct(
        public mixed $default = self::DEFAULT_PAGE_LENGTH,
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
