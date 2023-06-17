<?php

namespace Wexample\SymfonyApi;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Wexample\SymfonyDesignSystem\AbstractDesignSystemBundle;

class WexampleSymfonyApiBundle extends AbstractDesignSystemBundle
{
    public function build(ContainerBuilder $container): void
    {
        $this->addFrontPathCompilerPass(
            $container,
            __DIR__.'/../front',
        );
    }
}
