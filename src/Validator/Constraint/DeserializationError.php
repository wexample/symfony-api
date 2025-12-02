<?php

namespace Wexample\SymfonyApi\Validator\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Constraint for deserialization errors.
 */
class DeserializationError extends Constraint
{
    public string $message = 'Deserialization error: {{ message }}';
    public string $errorMessage;
    public string $propertyName;

    public function __construct(string $errorMessage, string $propertyName, array $options = null)
    {
        $this->errorMessage = $errorMessage;
        $this->propertyName = $propertyName;

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
        return DeserializationErrorValidator::class;
    }
}
