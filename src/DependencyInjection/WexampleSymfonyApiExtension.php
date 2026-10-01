<?php

namespace Wexample\SymfonyApi\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Wexample\SymfonyHelpers\DependencyInjection\AbstractWexampleSymfonyExtension;

class WexampleSymfonyApiExtension extends AbstractWexampleSymfonyExtension
{
    public function load(
        array $configs,
        ContainerBuilder $container
    ): void {
        $this->loadConfig(
            __DIR__,
            $container
        );

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter(
            'api_pretty_print',
            $config['pretty_print']
        );

        $container->setParameter(
            'api_test_error_log_length',
            $config['test_error_log_length']
        );

        $machineToken = $config['machine_token'];
        $container->setParameter('api_machine_token_class', $machineToken['token_class']);
        $container->setParameter('api_machine_token_prefix', $machineToken['prefix']);
        $container->setParameter('api_machine_token_roles', $machineToken['roles']);
        $container->setParameter('api_machine_token_last_used_interval', $machineToken['last_used_interval']);
    }
}
