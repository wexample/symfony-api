<?php

namespace Wexample\SymfonyApi\Api\Attribute\QueryOption\Trait;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Type;

trait QueryOptionConstrainedTrait
{
    use QueryOptionTrait;

    public mixed $default = null;

    abstract public function getConstraint(): Constraint;

    public function buildConstraintType(string $type): Constraint
    {
        return new Type($type);
    }

    public function getRequestValue(Request $request): mixed
    {
        return $request->get($this->key) ?: $this->default;
    }
}
