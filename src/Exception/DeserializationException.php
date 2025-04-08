<?php

namespace Wexample\SymfonyApi\Exception;

/**
 * Exception for deserialization errors in DTOs.
 *
 * This exception handles errors that occur during the deserialization process
 * of data into DTOs, providing detailed information about the failure.
 */
class DeserializationException extends AbstractApiException
{
    public const string CODE_TYPE_MISMATCH = 'TYPE_MISMATCH';

    /**
     * Creates a new deserialization exception.
     */
    public function __construct(
        \Throwable $previous,
        int $code = 0,
        ?string $internalCodeSuffix = self::CODE_TYPE_MISMATCH,
        array $context = [],
    )
    {
        parent::__construct(
            'Deserialization error: ' . $previous->getMessage(),
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
