<?php

namespace Wexample\SymfonyApi\Tests\Fixtures\App;

use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Wexample\SymfonyApi\WexampleSymfonyApiBundle;
use Wexample\SymfonyLoader\WexampleSymfonyLoaderBundle;
use Wexample\SymfonyTesting\Tests\Fixtures\AbstractFixtureKernel;
use Wexample\SymfonyTranslations\WexampleSymfonyTranslationsBundle;

class AppKernel extends AbstractFixtureKernel
{
    protected function getFixtureDir(): string
    {
        return __DIR__;
    }

    protected function getExtraBundles(): iterable
    {
        return [
            new SecurityBundle(),
            new WexampleSymfonyLoaderBundle(),
            new WexampleSymfonyTranslationsBundle(),
            new WexampleSymfonyApiBundle(),
        ];
    }

    protected function getConfigFiles(): array
    {
        return [
            __DIR__ . '/config/config.yaml',
        ];
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__ . '/Controller/', 'attribute');
    }
}
