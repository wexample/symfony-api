<?php

namespace Wexample\SymfonyApi;

use Wexample\SymfonyDesignSystem\Interface\DesignSystemBundleInterface;
use Wexample\SymfonyHelpers\Class\AbstractBundle;
use Wexample\SymfonyHelpers\Helper\BundleHelper;

class WexampleSymfonyApiBundle extends AbstractBundle implements DesignSystemBundleInterface
{
    public static function getDesignSystemFrontPaths(): array
    {
        return [
            BundleHelper::getBundleCssAlias(static::class) => __DIR__.'/../assets/',
        ];
    }
}
