<?php

namespace Wexample\SymfonyApi\Exception;

use Wexample\SymfonyHelpers\Exception\AbstractException;

abstract class AbstractApiException extends AbstractException
{
    function getInternalCodePrefix(): string
    {
        return 'API-';
    }
}
