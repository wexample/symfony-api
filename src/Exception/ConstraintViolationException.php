<?php

namespace Wexample\SymfonyApi\Exception;

use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Exception for constraint violations in DTOs.
 *
 * This exception takes a ConstraintViolationList and formats it for API responses.
 */
class ConstraintViolationException extends AbstractApiException
{
    public const string CODE_SPECIFIC_NAME = 'SPECIFIC_NAME';

    /**
     * Creates a new constraint violation exception.
     *
     * @param string $message The main error message
     * @param ConstraintViolationListInterface $violations The list of constraint violations
     * @param int $code The exception code
     * @param \Throwable|null $previous The previous exception if nested
     */
    public function __construct(
        string $message,
        ConstraintViolationListInterface $violations,
        int $code = 0,
        ?string $internalCode = null,
        array $context = [],
        \Throwable $previous = null
    )
    {
        parent::__construct(
            $this->formatErrorMessage($message, $violations),
            $code,
            $internalCode,
            $context,
            $previous
        );
    }

    function getApiInternalCodeParts(): array
    {
        return [
            'CV',
        ];
    }

    /**
     * Formats the error message to include detailed information about violations.
     *
     * @param string $message The main error message
     * @param ConstraintViolationListInterface $violations The list of constraint violations
     * @return string The formatted error message
     */
    private function formatErrorMessage(
        string $message,
        ConstraintViolationListInterface $violations
    ): string
    {
        if (count($violations) === 0) {
            return $message;
        }

        $formattedMessage = $message . ":\n";

        foreach ($violations as $index => $violation) {
            $formattedMessage .= "\n" . ($index + 1) . ". ";

            $parts = [];

            if ($property = $violation->getPropertyPath()) {
                $parts[] = "property: " . $property;
            }

            $parts[] = "message: " . $violation->getMessage();

            if ($code = $violation->getCode()) {
                $parts[] = "code: " . $code;
            }

            $formattedMessage .= implode("\n", $parts);
        }

        return $formattedMessage;
    }
}
