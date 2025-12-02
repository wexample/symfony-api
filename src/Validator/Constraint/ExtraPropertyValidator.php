<?php

namespace Wexample\SymfonyApi\Validator\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Validator for ExtraProperty constraint.
 */
class ExtraPropertyValidator extends ConstraintValidator
{
    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint): void
    {
        if (! $constraint instanceof ExtraProperty) {
            throw new UnexpectedTypeException($constraint, ExtraProperty::class);
        }

        // Similar to MissingRequiredPropertyValidator, this validator is used to create
        // violations for properties that shouldn't exist in the data, so we just add the violation.
        $this->context->buildViolation($constraint->message)
            ->setParameter('{{ property }}', $constraint->propertyName)
            ->setCode('extra_field')
            ->atPath($constraint->propertyName)
            ->addViolation();
    }
}
