<?php

namespace Wexample\SymfonyApi\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class TwigPathCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $definition = $container->getDefinition('twig.loader.native_filesystem');

        $definition->addMethodCall(
            'addPath',
            [
                __DIR__.'/../../../front',
                'WexampleSymfonyApiBundle',
            ]
        );
    }
}
