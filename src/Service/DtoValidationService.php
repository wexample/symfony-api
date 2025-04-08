<?php

namespace Wexample\SymfonyApi\Service;

use JsonException;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Wexample\SymfonyApi\Api\Attribute\ValidateRequestContent;
use Wexample\SymfonyApi\Api\Dto\AbstractDto;
use Wexample\SymfonyApi\Exception\ConstraintViolationException;
use Wexample\SymfonyApi\Exception\DeserializationException;
use Wexample\SymfonyApi\Exception\FieldValidationException;
use Wexample\SymfonyApi\Exception\ValidationException;
use Wexample\SymfonyApi\Validator\Constraint\ExtraProperty;
use Wexample\SymfonyApi\Validator\Constraint\JsonEncodingError;
use Wexample\SymfonyApi\Validator\Constraint\MissingRequiredProperty;
use Wexample\SymfonyHelpers\Helper\DataHelper;

class DtoValidationService
{
    /**
     * Cache for reflection classes to avoid repeated instantiation
     * @var array<string, ReflectionClass>
     */
    private array $reflectionCache = [];

    public function __construct(
        private readonly ValidatorInterface $validator,
        private readonly SerializerInterface $serializer,
    )
    {
    }

    /**
     * Validates a DTO from request content.
     *
     * @param Request $request
     * @param ValidateRequestContent $instance
     * @return AbstractDto|null
     * @throws ReflectionException
     */
    public function validateDtoFromRequest(
        Request $request,
        ValidateRequestContent $instance
    ): ?AbstractDto
    {
        /** @var AbstractDto|string $dtoClassType */
        $dtoClassType = $instance->dto;

        $content = null;
        $contentString = '';

        // Check if request is multipart/form-data
        if (str_contains($request->headers->get('Content-Type', ''), 'multipart/form-data')) {
            // Get all available field names from the request
            // Get valid field names based on patterns
            $validFields = $instance->getValidFieldNames(
                array_merge(
                    array_keys($request->request->all()),
                    array_keys($request->files->all())
                )
            );

            // Look for JSON data in valid fields
            foreach ($validFields as $fieldName) {
                $jsonData = $request->request->get($fieldName);
                if ($jsonData) {
                    $content = json_decode($jsonData, true);
                    $contentString = $jsonData;
                    break;
                }
            }
        } else {
            $contentString = $request->getContent();
            $content = json_decode($contentString, true);
        }

        if (!$content) {
            return null;
        }

        $dto = $this->validateDto(
            $content,
            $contentString,
            $dtoClassType
        );

        // If validation succeeded and we have files in the request, process them
        if ($dto !== null && $request->files->count() > 0) {
            $files = $request->files->all();

            // Force real MIME type detection
            foreach ($files as $file) {
                if ($file instanceof UploadedFile) {
                    // This triggers real MIME type detection
                    $file->getMimeType();
                }
            }

            $dto->setFiles($files);

            if ($filesConstraints = $dtoClassType::getFilesConstraints()) {
                $errors = $this->validator->validate($dto->getFiles(), $filesConstraints);
                if (count($errors) > 0) {
                    throw new ConstraintViolationException(
                        'At least one constraint has been violated in sent files.',
                        $errors
                    );
                }
            }
        }

        return $dto;
    }

    /**
     * Validates a DTO from content data.
     *
     * @param array $content The content data as array
     * @param string $contentString The content as JSON string
     * @param AbstractDto|string $dtoClassType The DTO class type
     * @return AbstractDto
     * @throws ReflectionException
     */
    public function validateDto(
        array $content,
        string $contentString,
        string $dtoClassType
    ): AbstractDto
    {
        // Check required keys
        $requiredKeys = $dtoClassType::getRequiredProperties();
        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $content)) {
                // Create a violation list with a single violation for the missing property
                $violations = $this->validator->validate(null, new MissingRequiredProperty($key));

                throw new ConstraintViolationException(
                    "The key '{$key}' is missing in the data.",
                    $violations
                );
            }
        }

        // Check for extra properties not defined in the DTO
        $this->validateExtraProperties($content, $dtoClassType);

        // Validate constraints
        $constraints = $dtoClassType::getConstraints();

        // Pre check data with constraints if available
        if ($constraints !== null) {
            // Add Optional constraint to all properties not explicitly constrained
            $reflectionClass = $this->getReflectionClass($dtoClassType);

            // Add every property name allows the field to exist in content.
            foreach ($reflectionClass->getProperties() as $property) {
                $key = $property->getName();
                if (!isset($constraints->fields[$key])) {
                    $constraints->fields[$key] = new Optional();
                }
            }

            // Validate input data against constraints
            $errors = $this->validator->validate($content, $constraints);

            if (count($errors) > 0) {
                // Create a specific exception for constraint violations
                throw new ConstraintViolationException(
                    'At least one constraint has been violated.',
                    $errors
                );
            }
        }

        try {
            // Constraints passed, now we create the actual dto.
            $dto = $this->serializer->deserialize(
                $contentString,
                $dtoClassType,
                DataHelper::FORMAT_JSON
            );
        } catch (\Throwable $e) {
            throw new DeserializationException(
                previous: $e,
                internalCode: DeserializationException::CODE_TYPE_MISMATCH
            );
        }

        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            throw new FieldValidationException(
                $errors,
                internalCode: FieldValidationException::CODE_FIELD_CONSTRAINT_VIOLATION
            );
        }

        return $dto;
    }

    /**
     * Validates that the input data doesn't contain properties that are not defined in the DTO class.
     *
     * @param array $content The content data as array
     * @param string $dtoClassType The DTO class type
     * @return bool True if validation passes, false otherwise
     * @throws ReflectionException
     */
    private function validateExtraProperties(
        array $content,
        string $dtoClassType
    ): void
    {
        $reflectionClass = $this->getReflectionClass($dtoClassType);
        $allowedProperties = [];

        // Get all properties defined in the DTO class
        foreach ($reflectionClass->getProperties() as $property) {
            $allowedProperties[] = $property->getName();
        }

        // Check for extra properties
        $extraProperties = [];
        foreach (array_keys($content) as $key) {
            if (!in_array($key, $allowedProperties)) {
                $extraProperties[] = $key;
            }
        }

        if (!empty($extraProperties)) {
            // Create violations for each extra property
            $violations = new \Symfony\Component\Validator\ConstraintViolationList();

            foreach ($extraProperties as $property) {
                // Add violations from the ExtraProperty constraint
                $propertyViolations = $this->validator->validate(null, new ExtraProperty($property));
                foreach ($propertyViolations as $violation) {
                    $violations->add($violation);
                }
            }

            throw new ConstraintViolationException(
                'The request contains unexpected properties: ' .
                implode(', ', $extraProperties) . '. ' .
                'Allowed properties are: ' . implode(', ', $allowedProperties) . '.',
                $violations
            );
        }
    }

    /**
     * Gets a ReflectionClass instance for the given class, using cache if available
     *
     * @param string $className The class name
     * @return ReflectionClass The reflection class instance
     * @throws ReflectionException
     */
    private function getReflectionClass(string $className): ReflectionClass
    {
        if (!isset($this->reflectionCache[$className])) {
            $this->reflectionCache[$className] = new ReflectionClass($className);
        }

        return $this->reflectionCache[$className];
    }


    /**
     * Validates data and creates a DTO instance.
     *
     * @param array $data The data to validate and use for DTO creation
     * @param string $dtoClass The DTO class to instantiate
     * @return AbstractDto The created DTO instance
     * @throws JsonException When JSON encoding fails
     * @throws ValidationException When validation fails
     * @throws ReflectionException
     */
    public function validateAndCreateDto(
        array $data,
        string $dtoClass
    ): AbstractDto
    {
        // Convert array to JSON string
        try {
            $jsonString = json_encode($data, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            // Create violations for JSON encoding error
            $violations = $this->validator->validate(null, new JsonEncodingError($e->getMessage()));

            throw new ConstraintViolationException(
                'Failed to encode data to JSON: ' . $e->getMessage(),
                $violations,
                $e->getCode(),
                $e
            );
        }

        return $this->validateDto(
            $data,
            $jsonString,
            $dtoClass
        );
    }
}
