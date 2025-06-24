<?php

namespace Wexample\SymfonyApi\Validator\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Constraint for JSON encoding errors.
 */
class JsonEncodingError extends Constraint
{
    public string $message = 'Failed to encode data to JSON: {{ message }}';
    public string $errorMessage;

    public function __construct(string $errorMessage, array $options = null)
    {
        $this->errorMessage = $errorMessage;
        
        parent::__construct($options);
    }

    public function getDefaultOption(): string
    {
        return 'errorMessage';
    }

    /**
     * {@inheritdoc}
     */
    public function validatedBy(): string
    {
        return JsonEncodingErrorValidator::class;
    }
}
