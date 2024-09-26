<?php

namespace Wexample\SymfonyApi\Api\Dto;

use Symfony\Component\Validator\Constraints\Collection;

abstract class AbstractDto
{
    public static function getConstraints(): ?Collection
    {
        return null;
    }
}
