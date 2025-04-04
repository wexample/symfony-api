<?php

namespace Wexample\SymfonyApi\Validator\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Constraint for extra properties not defined in DTOs.
 */
class ExtraProperty extends Constraint
{
    public string $message = 'The property "{{ property }}" is not defined in the DTO.';
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
        return ExtraPropertyValidator::class;
    }
}
