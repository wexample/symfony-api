<?php

namespace Wexample\SymfonyApi\Exception;

use Symfony\Component\Validator\ConstraintViolationListInterface;
use Wexample\SymfonyApi\Api\Dto\AbstractDto;

/**
 * Exception for field constraint validation errors in DTOs.
 *
 * This exception handles errors that occur during the validation process
 * of DTOs, providing detailed information about field validation failures.
 */
class FieldValidationException extends ConstraintViolationException
{
    public const string CODE_FIELD_CONSTRAINT_VIOLATION = 'FIELD_CONSTRAINT_VIOLATION';

    /**
     * Creates a new field validation exception.
     */
    public function __construct(
        ConstraintViolationListInterface $violations,
        AbstractDto $dto,
        int $code = 0,
        ?string $internalCodeSuffix = self::CODE_FIELD_CONSTRAINT_VIOLATION,
        array $context = [],
        \Throwable $previous = null
    )
    {
        parent::__construct(
            sprintf(
                'At least one field constraint has been violated during validation of DTO : %s',
                get_class($dto),
            ),
            $violations,
            $code,
            $internalCodeSuffix,
            $context,
            $previous
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getApiInternalCodeParts(): array
    {
        return [
            ...parent::getApiInternalCodeParts(),
            'FIELD'
        ];
    }
}
