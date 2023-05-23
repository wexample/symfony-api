<?php

namespace Wexample\SymfonyApi\Api\Attribute\QueryOption\Trait;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Type;

trait QueryOptionTrait
{
    public string $key;

    public mixed $default = null;

    public bool $required = false;

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
