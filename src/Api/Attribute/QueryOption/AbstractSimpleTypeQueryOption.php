<?php

namespace Wexample\SymfonyApi\Api\Attribute\QueryOption;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Type;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\Trait\QueryOptionConstrainedTrait;

abstract class AbstractSimpleTypeQueryOption extends AbstractQueryOption
{
    use QueryOptionConstrainedTrait;

    public function __construct(
        public string $key,
        mixed $default = null,
        bool $required = false,
    ) {
        $this->default = $default;

        parent::__construct(
            $required
        );
    }

    public function getConstraint(): Constraint
    {
        return new Type($this->getSimpleTypeConstraint());
    }

    abstract function getSimpleTypeConstraint(): string;
}
