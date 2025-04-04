<?php

namespace Wexample\SymfonyApi\Validator\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Validator for JsonEncodingError constraint.
 */
class JsonEncodingErrorValidator extends ConstraintValidator
{
    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof JsonEncodingError) {
            throw new UnexpectedTypeException($constraint, JsonEncodingError::class);
        }

        // This validator is used to create violations for JSON encoding errors
        $this->context->buildViolation($constraint->message)
            ->setParameter('{{ message }}', $constraint->errorMessage)
            ->setCode('json_encoding_error')
            ->addViolation();
    }
}
