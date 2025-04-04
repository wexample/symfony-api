<?php

namespace Wexample\SymfonyApi\Exception;

use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;

class ConstraintViolationException extends \RuntimeException
{
    /**
     * @var array
     */
    private array $errors;

    /**
     * ConstraintViolationException constructor.
     *
     * @param string $message
     * @param ConstraintViolationListInterface $violations
     * @param int $code
     * @param \Throwable|null $previous
     */
    public function __construct(
        string $message, 
        ConstraintViolationListInterface $violations, 
        int $code = 0, 
        \Throwable $previous = null
    ) {
        // Format the error message to include violation details
        $formattedMessage = $this->formatErrorMessage($message, $violations);
        
        // Convert violations to array format for API responses
        $this->errors = $this->violationsToArray($violations);
        
        parent::__construct($formattedMessage, $code, $previous);
    }

    /**
     * Get validation errors.
     *
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
    
    /**
     * Format the error message to include violation details.
     *
     * @param string $message
     * @param ConstraintViolationListInterface $violations
     * @return string
     */
    private function formatErrorMessage(string $message, ConstraintViolationListInterface $violations): string
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
     * Convert ConstraintViolationList to array format.
     *
     * @param ConstraintViolationListInterface $violations
     * @return array
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
     * Convert a single ConstraintViolation to array format.
     *
     * @param ConstraintViolationInterface $violation
     * @return array
     */
    private function violationToArray(ConstraintViolationInterface $violation): array
    {
        return [
            'message' => $violation->getMessage(),
            'property' => $violation->getPropertyPath(),
            'code' => $violation->getCode(),
            'value' => $this->formatValue($violation->getInvalidValue()),
        ];
    }
    
    /**
     * Format a value for inclusion in error messages.
     * Handles complex types like arrays and objects.
     *
     * @param mixed $value
     * @return mixed
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
