<?php

namespace Wexample\SymfonyApi\Exception;

use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Exception for file validation errors in DTOs.
 *
 * This exception handles errors that occur during the validation process
 * of uploaded files, providing detailed information about file validation failures.
 */
class FileValidationException extends ConstraintViolationException
{
    public const string CODE_FILE_CONSTRAINT_VIOLATION = 'FILE_CONSTRAINT_VIOLATION';
    
    /**
     * Creates a new file validation exception.
     */
    public function __construct(
        ConstraintViolationListInterface $violations,
        int $code = 0,
        ?string $internalCode = null,
        array $context = [],
        \Throwable $previous = null
    )
    {
        parent::__construct(
            'At least one constraint has been violated in sent files.',
            $violations,
            $code,
            $internalCode,
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
            'FILE'
        ];
    }
}
