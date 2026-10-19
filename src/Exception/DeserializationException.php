<?php

namespace Wexample\SymfonyApi\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

/**
 * Exception for deserialization errors in DTOs.
 *
 * This exception handles errors that occur during the deserialization process
 * of data into DTOs, providing detailed information about the failure.
 * The caller's mistake: answered, and logged, as a bad request.
 */
#[WithHttpStatus(400)]
class DeserializationException extends AbstractApiException
{
    public const string CODE_TYPE_MISMATCH = 'TYPE_MISMATCH';

    /**
     * Creates a new deserialization exception.
     */
    public function __construct(
        \Throwable $previous,
        string $message = 'Deserialization error',
        int $code = 0,
        ?string $internalCodeSuffix = self::CODE_TYPE_MISMATCH,
        array $context = [],
    ) {
        parent::__construct(
            $message . ': ' . $previous->getMessage(),
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
            'DESERIAL',
        ];
    }
}
