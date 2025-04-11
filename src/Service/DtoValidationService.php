<?php

namespace Wexample\SymfonyApi\Service;

use ReflectionClass;
use ReflectionException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Wexample\SymfonyApi\Api\Attribute\ValidateRequestContent;
use Wexample\SymfonyApi\Api\Dto\AbstractDto;
use Wexample\SymfonyApi\Exception\DeserializationException;
use Wexample\SymfonyApi\Exception\ExtraPropertyException;
use Wexample\SymfonyApi\Exception\FieldValidationException;
use Wexample\SymfonyApi\Exception\FileValidationException;
use Wexample\SymfonyApi\Exception\InputValidationException;
use Wexample\SymfonyApi\Exception\MissingRequiredPropertyException;
use Wexample\SymfonyApi\Validator\Constraint\ExtraProperty;
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

        $dto = $this->createDto(
            $content,
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
                    throw new FileValidationException(
                        $errors,
                    );
                }
            }
        }

        return $dto;
    }

    /**
     * Recursively validates raw data against the defined DTO structure.
     *
     * @param array $content The raw data as an associative array.
     * @param string $dtoClassType The fully qualified name of the DTO class.
     *
     * @throws MissingRequiredPropertyException When a required property is missing.
     */
    protected function validateRawDataRecursive(
        array $content,
        string $dtoClassType
    )
    {
        // Create a ReflectionClass instance to inspect all properties of the DTO class.
        $reflection = new \ReflectionClass($dtoClassType);
        $properties = $reflection->getProperties();

        // Retrieve the list of required properties from the DTO.
        $requiredProperties = $dtoClassType::getRequiredProperties();

        // Iterate through all defined properties.
        foreach ($properties as $property) {
            $propertyName = $property->getName();

            // If the property is required but not present in the content, raise an exception.
            if (in_array($propertyName, $requiredProperties) && !array_key_exists($propertyName, $content)) {
                $violations = $this->validator->validate(null, new MissingRequiredProperty($propertyName));
                throw new MissingRequiredPropertyException($propertyName, $violations);
            }

            // If the data for this property exists in the content...
            if (array_key_exists($propertyName, $content)) {
                $propertyType = $property->getType();

                if ($propertyType instanceof \ReflectionUnionType) {
                    $types = $propertyType->getTypes();
                } else {
                    $types = [$propertyType->getName()];
                }

                foreach ($types as $type) {
                    if (is_subclass_of($type, AbstractDto::class)) {
                        $this->validateRawDataRecursive($content[$propertyName], $type);
                    } elseif (is_array($content[$propertyName])) {
                        $allAttributes = $property->getAttributes(All::class);

                        foreach ($allAttributes as $allAttribute) {
                            $allConstraint = $allAttribute->newInstance();
                            foreach ($allConstraint->constraints as $constraint) {
                                if ($constraint instanceof Type) {
                                    foreach ($content[$propertyName] as $item) {
                                        $this->validateRawDataRecursive($item, $constraint->type);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
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
    public function createDto(
        array $content,
        string $dtoClassType
    ): AbstractDto
    {
        $this->validateRawDataRecursive(
            $content,
            $dtoClassType
        );

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
                throw new InputValidationException(
                    $errors,
                );
            }
        }

        try {
            // Constraints passed, now we create the actual dto.
            $dto = $this->serializer->deserialize(
                json_encode($content),
                $dtoClassType,
                DataHelper::FORMAT_JSON
            );
        } catch (\Throwable $e) {
            throw new DeserializationException(
                previous: $e,
            );
        }

        // Validate the DTO itself first
        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            throw new FieldValidationException(
                $errors,
            );
        }

        // Then validate all nested DTOs recursively
        $this->validateDtoRecursively($dto);

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
            $violations = new ConstraintViolationList();

            foreach ($extraProperties as $property) {
                // Add violations from the ExtraProperty constraint
                $propertyViolations = $this->validator->validate(null, new ExtraProperty($property));
                foreach ($propertyViolations as $violation) {
                    $violations->add($violation);
                }
            }

            throw new ExtraPropertyException(
                $extraProperties,
                $allowedProperties,
                $violations,
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
     * Validates a nested DTO and continues recursive validation.
     *
     * @param AbstractDto $dto The nested DTO to validate
     * @throws FieldValidationException If validation fails
     */
    private function validateNestedDto(AbstractDto $dto): void
    {
        // Validate the nested DTO
        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            throw new FieldValidationException($errors);
        }

        // Continue recursion
        $this->validateDtoRecursively($dto);
    }

    /**
     * Recursively validates a DTO and all its nested DTOs.
     *
     * @param AbstractDto $dto The DTO to validate recursively
     * @throws FieldValidationException If validation fails for any nested DTO
     */
    private function validateDtoRecursively(AbstractDto $dto): void
    {
        $reflection = new \ReflectionObject($dto);

        // Iterate through all properties of the DTO
        foreach ($reflection->getProperties() as $property) {
            if (!$property->isInitialized($dto)) {
                continue;
            }

            $value = $property->getValue($dto);

            // Skip null values
            if ($value === null) {
                continue;
            }

            // If the property is an array, check each element
            if (is_array($value)) {
                foreach ($value as $item) {
                    // If the item is a DTO, validate it recursively
                    if ($item instanceof AbstractDto) {
                        $this->validateNestedDto($item);
                    }
                }
            } // If the property is a DTO, validate it recursively
            elseif ($value instanceof AbstractDto) {
                $this->validateNestedDto($value);
            }
        }
    }
}
