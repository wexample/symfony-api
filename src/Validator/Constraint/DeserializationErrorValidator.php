<?php

namespace Wexample\SymfonyApi\Validator\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Validator for DeserializationError constraint.
 */
class DeserializationErrorValidator extends ConstraintValidator
{
    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint): void
    {
        if (! $constraint instanceof DeserializationError) {
            throw new UnexpectedTypeException($constraint, DeserializationError::class);
        }

        // This validator is used to create violations for deserialization errors
        $this->context->buildViolation($constraint->message)
            ->setParameter('{{ message }}', $constraint->errorMessage)
            ->setCode('type_error')
            ->atPath($constraint->propertyName)
            ->addViolation();
    }
}
