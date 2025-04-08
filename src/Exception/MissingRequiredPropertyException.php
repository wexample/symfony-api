<?php

namespace Wexample\SymfonyApi\Exception;

use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Exception for missing required properties in DTOs.
 *
 * This exception handles errors that occur when a required property
 * is missing from the input data during validation.
 */
class MissingRequiredPropertyException extends ConstraintViolationException
{
    public const string CODE_MISSING_REQUIRED_PROPERTY = 'MISSING_REQUIRED_PROPERTY';
    
    /**
     * The name of the missing property.
     */
    private string $propertyName;
    
    /**
     * Creates a new missing required property exception.
     *
     * @param string $propertyName The name of the missing property
     * @param ConstraintViolationListInterface $violations The list of constraint violations
     * @param int $code The exception code
     * @param string|null $internalCode The internal error code
     * @param array $context Additional context data
     * @param \Throwable|null $previous The previous exception if nested
     */
    public function __construct(
        string $propertyName,
        ConstraintViolationListInterface $violations,
        int $code = 0,
        ?string $internalCode = null,
        array $context = [],
        \Throwable $previous = null
    )
    {
        $this->propertyName = $propertyName;
        
        parent::__construct(
            "The key '{$propertyName}' is missing in the data.",
            $violations,
            $code,
            $internalCode,
            $context,
            $previous
        );
    }
    
    /**
     * Gets the name of the missing property.
     *
     * @return string The property name
     */
    public function getPropertyName(): string
    {
        return $this->propertyName;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getApiInternalCodeParts(): array
    {
        return [
            ...parent::getApiInternalCodeParts(),
            'MISSING'
        ];
    }
}
