<?php

namespace Wexample\SymfonyApi\Service;

use JsonException;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Wexample\SymfonyApi\Api\Attribute\ValidateRequestContent;
use Wexample\SymfonyApi\Api\Dto\AbstractDto;
use Wexample\SymfonyApi\Exception\ValidationException;
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
     * @param callable $errorCallback
     * @return AbstractDto|null
     * @throws ReflectionException
     */
    public function validateDtoFromRequest(
        Request $request,
        ValidateRequestContent $instance,
        callable $errorCallback
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
            $dtoClassType,
            $errorCallback
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
                    $errorCallback(
                        'At least one constraint has been violated in sent files.',
                        $errors
                    );
                    return null;
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
     * @param string $dtoClassType The DTO class type
     * @param callable $errorCallback Callback for error handling: function(\Throwable $exception = null, ?ConstraintViolationListInterface $violations = null)
     * @return AbstractDto|null
     * @throws ReflectionException
     */
    public function validateDto(
        array $content,
        string $contentString,
        string $dtoClassType,
        callable $errorCallback
    ): ?AbstractDto
    {
        // Validate required keys
        $requiredKeys = $dtoClassType::getRequiredProperties();
        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $content)) {
                // Create a custom violation list with property path information
                $customViolation = new \Symfony\Component\Validator\ConstraintViolationList([
                    new \Symfony\Component\Validator\ConstraintViolation(
                        "The key '{$key}' is missing in the data.",
                        "The key '{{ property }}' is missing in the data.",
                        ['{{ property }}' => $key],
                        null,
                        $key, // Use the missing key as the property path
                        null,
                        null,
                        'required_property'
                    )
                ]);
                
                // Create a specific exception for missing required property
                $exception = new \Wexample\SymfonyApi\Exception\ValidationException(
                    "The key '{$key}' is missing in the data.",
                    [[
                        'message' => "The key '{$key}' is missing in the data.",
                        'property' => $key
                    ]]
                );
                
                $errorCallback(
                    $exception,
                    $customViolation
                );
                return null;
            }
        }

        // Check for extra properties not defined in the DTO
        if (!$this->validateExtraProperties($content, $dtoClassType, $errorCallback)) {
            return null;
        }

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
                $exception = new \Wexample\SymfonyApi\Exception\ValidationException(
                    'At least one constraint has been violated.',
                    $this->formatViolationsToArray($errors)
                );
                
                $errorCallback(
                    $exception,
                    $errors
                );
                return null;
            }
        }

        try {
            // Constraints passed, now we create the actual dto.
            $dto = $this->serializer->deserialize(
                $contentString,
                $dtoClassType,
                DataHelper::FORMAT_JSON
            );

            // Comprehensive validation approach:
            // 1. Validate the DTO object itself (validates annotations on DTO properties)
            $errors = $this->validator->validate($dto);

            // 2. If we have specific constraints defined in the DTO class, validate content against them
            // This is important as some constraints might only be defined in getConstraints() method
            if ($constraints !== null) {
                $additionalErrors = $this->validator->validate(
                    $content,
                    $constraints
                );

                $errors->addAll($additionalErrors);
            }

            // 3. General validation of content (catches any violations not covered by the above)
            // This step is necessary to catch violations that might not be covered by explicit constraints
            $additionalErrors = $this->validator->validate(
                $content
            )
            ;
            $errors->addAll($additionalErrors);

            if (count($errors) > 0) {
                // Create a specific exception for field constraint violations
                $exception = new \Wexample\SymfonyApi\Exception\ValidationException(
                    'At least one field constraint has been violated',
                    $this->formatViolationsToArray($errors)
                );
                
                $errorCallback(
                    $exception,
                    $errors
                );
                return null;
            }

            return $dto;
        } catch (ExceptionInterface $e) {
            // Extract property name from deserialization error message
            $propertyName = 'unknown';
            $message = $e->getMessage();
            
            // Try to extract property name from the error message
            // Example: "The type of the \"userId\" attribute for class..."
            if (preg_match('/The type of the "([^"]+)" attribute/', $message, $matches)) {
                $propertyName = $matches[1];
            }
            
            // Create a custom violation list with property path information
            $customViolation = new \Symfony\Component\Validator\ConstraintViolationList([
                new \Symfony\Component\Validator\ConstraintViolation(
                    'Deserialization error: ' . $message,
                    'Deserialization error: {{ message }}',
                    ['{{ message }}' => $message],
                    null,
                    $propertyName, // Use the extracted property name as the property path
                    null,
                    null,
                    'deserialization_error'
                )
            ]);
            
            // Pass the original exception to the callback
            $errorCallback(
                $e,
                $customViolation
            );
            return null;
        } catch (JsonException $e) {
            // Handle JSON exceptions - pass the exception directly
            $errorCallback($e);
            return null;
        } catch (\Exception $e) {
            // Fallback for other exceptions - pass the exception directly
            $errorCallback($e);
            return null;
        }
    }

    /**
     * Validates that the input data doesn't contain properties that are not defined in the DTO class.
     *
     * @param array $content The content data as array
     * @param string $dtoClassType The DTO class type
     * @param callable $errorCallback Callback for error handling
     * @return bool True if validation passes, false otherwise
     * @throws ReflectionException
     */
    private function validateExtraProperties(
        array $content,
        string $dtoClassType,
        callable $errorCallback
    ): bool
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
            $errorCallback(
                'The request contains unexpected properties: ' .
                implode(', ', $extraProperties) . '. ' .
                'Allowed properties are: ' . implode(', ', $allowedProperties) . '.'
            );
            return false;
        }

        return true;
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
     * Formats a ConstraintViolationList to an array of violations
     * 
     * @param ConstraintViolationListInterface $violations
     * @return array
     */
    private function formatViolationsToArray(ConstraintViolationListInterface $violations): array
    {
        $result = [];
        
        foreach ($violations as $violation) {
            $result[] = [
                'message' => $violation->getMessage(),
                'property' => $violation->getPropertyPath(),
                'code' => $violation->getCode(),
                'value' => $violation->getInvalidValue(),
            ];
        }
        
        return $result;
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
            throw new JsonException('Failed to encode data to JSON: ' . $e->getMessage(), $e->getCode(), $e);
        }

        // Create an error collector
        $errors = [];
        $errorCallback = function (
            \Throwable $exception = null,
            ?ConstraintViolationListInterface $violations = null
        ) use
        (
            &
            $errors
        ) {
            if ($violations) {
                foreach ($violations as $violation) {
                    $errors[] = [
                        'message' => $violation->getMessage(),
                        'property' => $violation->getPropertyPath(),
                        'code' => $violation->getCode(),
                        'value' => $violation->getInvalidValue(),
                    ];
                }
            } elseif ($exception instanceof \Wexample\SymfonyApi\Exception\ValidationException) {
                // If we have a ValidationException, use its errors directly
                $errors = array_merge($errors, $exception->getErrors());
            } elseif ($exception instanceof \Exception) {
                // For exceptions without violations, try to extract property information
                $message = $exception->getMessage();
                $propertyMatch = [];
                if (preg_match("/The key '([^']+)' is missing/", $message, $propertyMatch)) {
                    $errors[] = [
                        'message' => $message,
                        'property' => $propertyMatch[1] ?? ''
                    ];
                } else {
                    // Try to extract property name from serializer exceptions
                    $propertyName = 'unknown';
                    if (preg_match('/The type of the "([^"]+)" attribute/', $message, $matches)) {
                        $propertyName = $matches[1];
                    }
                    
                    $errors[] = [
                        'message' => $message,
                        'property' => $propertyName
                    ];
                }
            }
        };

        // Use the existing validateDto method
        $dto = $this->validateDto(
            $data,
            $jsonString,
            $dtoClass,
            $errorCallback
        );

        // If there are errors, throw a ValidationException
        if (!empty($errors)) {
            throw new ValidationException('Validation failed for DTO class ' . $dtoClass, $errors);
        }

        if ($dto === null) {
            throw new ValidationException('Failed to create DTO instance for class ' . $dtoClass);
        }

        return $dto;
    }
}
