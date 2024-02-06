<?php

namespace Wexample\SymfonyApi\Traits;

use Wexample\SymfonyApi\WexampleSymfonyApiBundle;
use Wexample\SymfonyHelpers\Traits\BundleClassTrait;

trait SymfonyApiBundleClassTrait
{
    use BundleClassTrait;

    public static function getBundleClassName(): string
    {
        return WexampleSymfonyApiBundle::class;
    }
}
