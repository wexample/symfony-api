<?php

namespace Wexample\SymfonyApi\Traits;

use Wexample\SymfonyApi\WexampleSymfonyApiBundle;

trait SymfonyApiBundleClassTrait
{
    public static function getBundleClassName(): string
    {
        return WexampleSymfonyApiBundle::class;
    }
}
