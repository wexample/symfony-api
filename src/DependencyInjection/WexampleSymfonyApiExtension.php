<?php

namespace Wexample\SymfonyApi\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Wexample\SymfonyDesignSystem\Traits\DesignSystemExtensionTrait;
use Wexample\SymfonyHelpers\DependencyInjection\AbstractWexampleSymfonyExtension;

class WexampleSymfonyApiExtension extends AbstractWexampleSymfonyExtension
{
    use DesignSystemExtensionTrait;

    public function load(
        array $configs,
        ContainerBuilder $container
    ) {
        $this->loadConfig(
            __DIR__,
            $container
        );

        $this->setTranslationPath(
            $container,
            __DIR__.'/../../front/'
        );
    }
}
