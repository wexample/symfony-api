<?php

namespace Wexample\SymfonyApi\Command\Traits;

use Wexample\SymfonyApi\WexampleSymfonyApiBundle;

trait AbstractSymfonyApiBundleCommandTrait
{
    public static function getBundle(): string
    {
        return WexampleSymfonyApiBundle::class;
    }
}
