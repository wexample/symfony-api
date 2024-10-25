<?php

namespace Wexample\SymfonyApi\Api\Dto;

use ReflectionClass;
use Symfony\Component\Validator\Constraints\Collection;
use Wexample\SymfonyApi\Api\Attribute\RequiredDtoProperty;

abstract class AbstractDto
{
    public static function getConstraints(): ?Collection
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
}
