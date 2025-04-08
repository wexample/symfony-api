<?php

namespace Wexample\SymfonyApi\Exception;

use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Exception for deserialization errors in DTOs.
 *
 * This exception handles errors that occur during the deserialization process
 * of data into DTOs, providing detailed information about the failure.
 */
class DeserializationException extends AbstractApiException
{
    public const string CODE_TYPE_MISMATCH = 'TYPE_MISMATCH';
    public const string CODE_INVALID_FORMAT = 'INVALID_FORMAT';
    
    /**
     * The list of constraint violations.
     */
    private ConstraintViolationListInterface $violations;
    
    /**
     * Creates a new deserialization exception.
     *
     * @param string $message The main error message
     * @param ConstraintViolationListInterface $violations The list of constraint violations
     * @param int $code The exception code
     * @param string|null $internalCode The internal error code
     * @param array $context Additional context data
     * @param \Throwable|null $previous The previous exception if nested
     */
    public function __construct(
        string $message,
        ConstraintViolationListInterface $violations,
        int $code = 0,
        ?string $internalCode = null,
        array $context = [],
        \Throwable $previous = null
    ) {
        $this->violations = $violations;
        
        parent::__construct(
            $message,
            $code,
            $internalCode,
            $context,
            $previous
        );
    }
    
    /**
     * Gets the validation errors as a ConstraintViolationListInterface.
     *
     * @return ConstraintViolationListInterface The validation errors
     */
    public function getViolations(): ConstraintViolationListInterface
    {
        return $this->violations;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getApiInternalCodeParts(): array
    {
        return [
            'DESERIAL',
        ];
    }
}
