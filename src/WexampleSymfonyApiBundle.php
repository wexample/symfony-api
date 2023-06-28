<?php

namespace Wexample\SymfonyApi;

use Wexample\SymfonyDesignSystem\Interface\DesignSystemBundleInterface;
use Wexample\SymfonyHelpers\AbstractBundle;

class WexampleSymfonyApiBundle extends AbstractBundle implements DesignSystemBundleInterface
{
    public static function getDesignSystemFrontPaths(): array
    {
        return [
            __DIR__.'/../assets/',
        ];
    }
}
