<?php

namespace Wexample\SymfonyApi\Exception;

/**
 * Exception for constraint violations in DTOs.
 *
 * This exception takes a ConstraintViolationList and formats it for API responses.
 */
class ConstraintViolationException extends AbstractApiException
{
    function getApiInternalCodeParts(): array
    {
        return [
            'CV',
        ];
    }
}
