<?php

namespace Wexample\SymfonyApi;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Wexample\SymfonyApi\DependencyInjection\Compiler\TwigPathCompilerPass;

class WexampleSymfonyApiBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(
            new TwigPathCompilerPass()
        );
    }
}
