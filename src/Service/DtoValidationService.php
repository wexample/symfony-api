<?php

namespace Wexample\SymfonyApi\Service;

use ReflectionClass;
use ReflectionException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Wexample\SymfonyApi\Api\Attribute\ValidateRequestContent;
use Wexample\SymfonyApi\Api\Dto\AbstractDto;
use Wexample\SymfonyHelpers\Helper\DataHelper;

class DtoValidationService
{
    public function __construct(
        private readonly ValidatorInterface $validator,
        private readonly SerializerInterface $serializer,
    ) {
    }

    /**
     * Validates a DTO from request content.
     *
     * @param Request $request
     * @param ValidateRequestContent $instance
     * @param callable $errorCallback
     * @return AbstractDto|null
     */
    public function validateDtoFromRequest(
        Request $request,
        ValidateRequestContent $instance,
        callable $errorCallback
    ): ?AbstractDto {
        /** @var AbstractDto $dtoClassType */
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
     * @param AbstractDto $dtoClassType The DTO class type
     * @param callable $errorCallback Callback for error handling
     * @return AbstractDto|null
     * @throws ReflectionException
     */
    public function validateDto(
        array $content,
        string $contentString,
        AbstractDto $dtoClassType,
        callable $errorCallback
    ): ?AbstractDto {
        // Validate required keys
        $requiredKeys = $dtoClassType::getRequiredProperties();
        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $content)) {
                $errorCallback(
                    "The key '{$key}' is missing in the data."
                );
                return null;
            }
        }

        // Validate constraints
        $constraints = $dtoClassType::getConstraints();

        // Pre check data.
        if ($constraints !== null) {
            $reflectionClass = new ReflectionClass($dtoClassType);

            // Add every property name allows the field to exist in content.
            foreach ($reflectionClass->getProperties() as $property) {
                $key = $property->getName();
                if (!isset($constraints->fields[$key])) {
                    $constraints->fields[$key] = new Optional();
                }
            }

            // First validate input data.
            $errors = $this->validator->validate(
                $content,
                $constraints
            );

            if (count($errors) > 0) {
                $errorCallback(
                    'At least one constraint has been violated.',
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

            $errors = $this->validator->validate($dto);

            // Checks specific constraints,
            // This check will allow fields that are not explicitly declared into getConstraints.
            if ($constraints !== null) {
                $additionalErrors = $this->validator->validate(
                    $content,
                    $constraints
                );

                $errors->addAll($additionalErrors);
            }

            // This check will inspect only properties that were not declared into getConstraints.
            $additionalErrors = $this->validator->validate(
                $content
            );

            $errors->addAll($additionalErrors);
            if (count($errors) > 0) {
                $errorCallback(
                    'At least one field constraint has been violated',
                    $errors
                );
                return null;
            }

            return $dto;
        } catch (\Exception $e) {
            // Some errors can remain on deserialization.
            $errorCallback(
                $e->getMessage()
            );
            return null;
        }
    }
}
