<?php

namespace Wexample\SymfonyApi\Exception;

use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Exception for extra properties in DTOs.
 *
 * This exception handles errors that occur when input data contains
 * properties that are not defined in the DTO class.
 */
class ExtraPropertyException extends ConstraintViolationException
{
    public const string CODE_EXTRA_PROPERTY = 'EXTRA_PROPERTY';

    /**
     * The list of extra properties found.
     *
     * @var array<string>
     */
    private array $extraProperties;

    /**
     * The list of allowed properties.
     *
     * @var array<string>
     */
    private array $allowedProperties;

    /**
     * Creates a new extra property exception.
     *
     * @param array<string> $extraProperties The list of extra properties found
     * @param array<string> $allowedProperties The list of allowed properties
     * @param ConstraintViolationListInterface $violations The list of constraint violations
     * @param int $code The exception code
     * @param string|null $internalCode The internal error code
     * @param array $context Additional context data
     * @param \Throwable|null $previous The previous exception if nested
     */
    public function __construct(
        array $extraProperties,
        array $allowedProperties,
        ConstraintViolationListInterface $violations,
        int $code = 0,
        ?string $internalCode = self::CODE_EXTRA_PROPERTY,
        array $context = [],
        \Throwable $previous = null
    )
    {
        $this->extraProperties = $extraProperties;
        $this->allowedProperties = $allowedProperties;

        $message = 'The request contains unexpected properties: ' .
            implode("', '", $extraProperties) . '. ' .
            'Allowed properties are: ' . implode("', '", $allowedProperties) . '.';

        parent::__construct(
            $message,
            $violations,
            $code,
            $internalCode,
            $context,
            $previous
        );
    }

    /**
     * Gets the list of extra properties found.
     *
     * @return array<string> The extra properties
     */
    public function getExtraProperties(): array
    {
        return $this->extraProperties;
    }

    /**
     * Gets the list of allowed properties.
     *
     * @return array<string> The allowed properties
     */
    public function getAllowedProperties(): array
    {
        return $this->allowedProperties;
    }

    /**
     * {@inheritdoc}
     */
    public function getApiInternalCodeParts(): array
    {
        return [
            ...parent::getApiInternalCodeParts(),
            'EXTRA'
        ];
    }
}