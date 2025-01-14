<?php

namespace Wexample\SymfonyApi\Api\Dto;

use ReflectionClass;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints as Assert;
use Wexample\SymfonyApi\Api\Attribute\RequiredDtoProperty;

abstract class AbstractDto
{
    /** @var \Symfony\Component\HttpFoundation\File\UploadedFile[] */
    protected array $files = [];

    public static function getConstraints(): ?Collection
    {
        return null;
    }

    public static function getFilesConstraints(): ?Assert\All
    {
        return null;
    }

    public static function getRequiredProperties(): array
    {
        $requiredProperties = [];
        $reflectionClass = new ReflectionClass(static::class);

        foreach ($reflectionClass->getProperties() as $property) {
            $attributes = $property->getAttributes(RequiredDtoProperty::class);

            if (!empty($attributes)) {
                $requiredProperties[] = $property->getName();
            }
        }

        return $requiredProperties;
    }

    /**
     * @param UploadedFile[] $files
     */
    public function setFiles(array $files): void
    {
        $this->files = $files;
    }

    /**
     * @return UploadedFile[]
     */
    public function getFiles(): array
    {
        return $this->files;
    }
}
