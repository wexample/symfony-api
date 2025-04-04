<?php

namespace Wexample\SymfonyApi\Validator\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Constraint for missing required properties in DTOs.
 */
class MissingRequiredProperty extends Constraint
{
    public string $message = 'The key "{{ key }}" is missing in the data.';
    public string $propertyName;

    public function __construct(string $propertyName, array $options = null)
    {
        $this->propertyName = $propertyName;
        
        parent::__construct($options);
    }

    public function getDefaultOption(): string
    {
        return 'propertyName';
    }

    /**
     * {@inheritdoc}
     */
    public function validatedBy(): string
    {
        return MissingRequiredPropertyValidator::class;
    }
}
