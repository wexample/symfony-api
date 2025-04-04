<?php

namespace Wexample\SymfonyApi\Validator\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Validator for MissingRequiredProperty constraint.
 */
class MissingRequiredPropertyValidator extends ConstraintValidator
{
    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof MissingRequiredProperty) {
            throw new UnexpectedTypeException($constraint, MissingRequiredProperty::class);
        }

        // This validator is special as it's used to create violations for properties
        // that don't exist in the data, so we don't actually validate anything here.
        // Instead, we just add the violation.
        $this->context->buildViolation($constraint->message)
            ->setParameter('{{ key }}', $constraint->propertyName)
            ->setCode('missing_required_field')
            ->atPath($constraint->propertyName)
            ->addViolation();
    }
}
