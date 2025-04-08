<?php

namespace Wexample\SymfonyApi\Exception;

use Wexample\SymfonyHelpers\Exception\AbstractException;

abstract class AbstractApiException extends AbstractException
{
    public function getInternalCodeParts(): array
    {
        return array_merge([
            'API'
        ], $this->getApiInternalCodeParts());
    }

    abstract function getApiInternalCodeParts(): array;
}
