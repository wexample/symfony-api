<?php

namespace Wexample\SymfonyApi\Api\Attribute\QueryOption\Trait;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Type;
use Wexample\SymfonyApi\Helper\ApiHelper;

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
        $value = ApiHelper::getRequestParameter($request, $this->key);

        // Not a falsy test: a zero is a value the caller meant, and the pagination
        // reads it as "no limit". Only an absent or empty parameter is a default.
        return ($value === null || $value === '') ? $this->default : $value;
    }
}
