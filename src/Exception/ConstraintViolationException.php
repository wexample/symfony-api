<?php

namespace Wexample\SymfonyApi\Exception;

use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Exception for constraint violations in DTOs.
 *
 * This exception takes a ConstraintViolationList and formats it for API responses.
 */
class ConstraintViolationException extends \RuntimeException
{
    /**
     * Array representation of the constraint violations for API responses.
     *
     * @var array
     */
    private array $errors;

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
        \Throwable $previous = null
    )
    {
        // Format the error message to include violation details
        $formattedMessage = $this->formatErrorMessage($message, $violations);

        // Convert violations to array format for API responses
        $this->errors = $this->violationsToArray($violations);

        parent::__construct($formattedMessage, $code, $previous);
    }

    /**
     * Gets the validation errors as an array for API responses.
     *
     * @return array The validation errors
     */
    public function getErrors(): array
    {
        return $this->errors;
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

            $formattedMessage .= implode(", ", $parts);
        }

        return $formattedMessage;
    }

    /**
     * Converts a ConstraintViolationList to an array format suitable for API responses.
     *
     * @param ConstraintViolationListInterface $violations The list of constraint violations
     * @return array The array representation of the violations
     */
    private function violationsToArray(ConstraintViolationListInterface $violations): array
    {
        $result = [];

        foreach ($violations as $violation) {
            $result[] = $this->violationToArray($violation);
        }

        return $result;
    }

    /**
     * Converts a single ConstraintViolation to an array format.
     *
     * @param ConstraintViolationInterface $violation The constraint violation
     * @return array The array representation of the violation
     */
    private function violationToArray(ConstraintViolationInterface $violation): array
    {
        // Get the message with parameters replaced
        $message = $violation->getMessage();

        // For missing required property violations, we need to manually format the message
        // since the parameters might not be properly interpolated
        $message = str_replace('{{ key }}', $violation->getPropertyPath(), $message);

        return [
            'message' => $message,
            'property' => $violation->getPropertyPath(),
            'code' => $violation->getCode(),
            'value' => $this->formatValue($violation->getInvalidValue()),
        ];
    }

    /**
     * Formats a value for inclusion in error messages.
     * Handles complex types like arrays and objects by providing a readable representation.
     *
     * @param mixed $value The value to format
     * @return mixed The formatted value
     */
    private function formatValue($value)
    {
        if (is_object($value) && !method_exists($value, '__toString')) {
            return get_class($value);
        }

        if (is_array($value)) {
            return '[array]';
        }

        return $value;
    }
}
