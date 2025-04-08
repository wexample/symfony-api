<?php

namespace Wexample\SymfonyApi\Exception;

use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Exception for JSON encoding errors in DTOs.
 *
 * This exception handles errors that occur when trying to encode
 * data to JSON format during validation.
 */
class JsonEncodingException extends ConstraintViolationException
{
    public const string CODE_JSON_ENCODING_ERROR = 'JSON_ENCODING_ERROR';

    /**
     * Creates a new JSON encoding exception.
     *
     * @param string $errorMessage The JSON error message
     * @param ConstraintViolationListInterface $violations The list of constraint violations
     * @param int $code The exception code
     * @param string|null $internalCode The internal error code
     * @param array $context Additional context data
     * @param \Throwable|null $previous The previous exception if nested
     */
    public function __construct(
        string $errorMessage,
        ConstraintViolationListInterface $violations,
        int $code = 0,
        ?string $internalCode = self::CODE_JSON_ENCODING_ERROR,
        array $context = [],
        \Throwable $previous = null
    )
    {
        parent::__construct(
            'Failed to encode data to JSON: ' . $errorMessage,
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
            'JSON'
        ];
    }
}