<?php

namespace Wexample\SymfonyApi\Exception;

class ValidationException extends \RuntimeException
{
    /**
     * @var array
     */
    private array $errors;

    /**
     * ValidationException constructor.
     *
     * @param string $message
     * @param array $errors
     * @param int $code
     * @param \Throwable|null $previous
     */
    public function __construct(string $message, array $errors = [], int $code = 0, \Throwable $previous = null)
    {
        // Format the error message to include error details
        $formattedMessage = $this->formatErrorMessage($message, $errors);
        
        parent::__construct($formattedMessage, $code, $previous);
        $this->errors = $errors;
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
     * Format the error message to include error details.
     * This method is agnostic to the error structure and will display all information available.
     *
     * @param string $message
     * @param array $errors
     * @return string
     */
    private function formatErrorMessage(string $message, array $errors): string
    {
        if (empty($errors)) {
            return $message;
        }
        
        $formattedMessage = $message . ":\n";
        
        foreach ($errors as $index => $error) {
            $formattedMessage .= "\n" . ($index + 1) . ". ";
            
            // If error is a string, just display it
            if (is_string($error)) {
                $formattedMessage .= $error;
                continue;
            }
            
            // If error is not an array, convert it to string representation
            if (!is_array($error)) {
                $formattedMessage .= json_encode($error);
                continue;
            }
            
            // For array errors, display all key-value pairs
            $errorParts = [];
            foreach ($error as $key => $value) {
                // Skip empty values
                if (empty($value) && $value !== 0 && $value !== '0') {
                    continue;
                }
                
                // Format the value based on its type
                if (is_array($value) || is_object($value)) {
                    $formattedValue = json_encode($value);
                } else {
                    $formattedValue = (string)$value;
                }
                
                $errorParts[] = $key . ": " . $formattedValue;
            }
            
            // If we have error parts, join them
            if (!empty($errorParts)) {
                $formattedMessage .= implode(", ", $errorParts);
            } else {
                // Fallback to json_encode if no parts were extracted
                $formattedMessage .= json_encode($error);
            }
        }
        
        return $formattedMessage;
    }
}
