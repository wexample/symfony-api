<?php

namespace Wexample\SymfonyApi\Exception;

use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Exception for input data validation errors in DTOs.
 *
 * This exception handles errors that occur during the validation process
 * of input data before deserialization into DTOs.
 */
class InputValidationException extends ConstraintViolationException
{
    public const string CODE_INPUT_CONSTRAINT_VIOLATION = 'INPUT_CONSTRAINT_VIOLATION';

    /**
     * Creates a new input validation exception.
     */
    public function __construct(
        ConstraintViolationListInterface $violations,
        int $code = 0,
        ?string $internalCodeSuffix = self::CODE_INPUT_CONSTRAINT_VIOLATION,
        array $context = [],
        \Throwable $previous = null
    )
    {
        parent::__construct(
            'At least one constraint has been violated.',
            $violations,
            $code,
            $internalCodeSuffix,
            $context,
            $previous
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getApiInternalCodeParts(): array
    {
        return [
            ...parent::getApiInternalCodeParts(),
            'INPUT'
        ];
    }
}