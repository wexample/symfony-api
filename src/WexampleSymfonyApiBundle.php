<?php

namespace Wexample\SymfonyApi;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Wexample\SymfonyDesignSystem\DependencyInjection\Compiler\DesignSystemTemplatesCompilerPass;

class WexampleSymfonyApiBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(
            new DesignSystemTemplatesCompilerPass(
                __DIR__.'/../front',
                'SymfonyApiBundle'
            )
        );
    }
}
