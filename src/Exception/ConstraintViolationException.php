<?php

namespace Wexample\SymfonyApi\Exception;

use Symfony\Component\Validator\ConstraintViolationListInterface;
use Wexample\SymfonyHelpers\Helper\ViolationHelper;

/**
 * Exception for constraint violations in DTOs.
 *
 * This exception takes a ConstraintViolationList and formats it for API responses.
 */
class ConstraintViolationException extends AbstractApiException
{
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
        protected ConstraintViolationListInterface $violations,
        int $code = 0,
        ?string $internalCodeSuffix = null,
        array $context = [],
        \Throwable $previous = null
    )
    {
        parent::__construct(
            $this->formatErrorMessage($message, $violations),
            $code,
            $internalCodeSuffix,
            $context,
            $previous
        );
    }

    public function getViolations(): ConstraintViolationListInterface
    {
        return $this->violations;
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
    public function formatErrorMessage(
        string $message,
        ConstraintViolationListInterface $violations
    ): string
    {
        if (count($violations) === 0) {
            return $message;
        }

        $formattedMessage = $message . ":" . PHP_EOL;

        foreach ($violations as $index => $violation) {
            $number = ($index + 1) . ". ";
            $formattedMessage .= PHP_EOL . $number;
            $indent = str_repeat(" ", strlen($number));

            $parts = [];

            if ($property = $violation->getPropertyPath()) {
                $parts[] = "property: " . $property;
            }

            $parts[] = $indent . "message: " . ViolationHelper::getFormattedMessage($violation);

            if ($code = $violation->getCode()) {
                $parts[] = $indent . "code: " . $code;
            }

            $formattedMessage .= implode(PHP_EOL, $parts);
        }

        return $formattedMessage;
    }
}
