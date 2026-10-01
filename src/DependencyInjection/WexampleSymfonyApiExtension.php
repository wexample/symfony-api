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
        $loader = $this->loadConfig(
            __DIR__,
            $container
        );

        // The application's API controllers, when it has that directory: a
        // glob on a missing one stops the container from compiling.
        if (is_dir($container->getParameter('kernel.project_dir') . '/src/Api/Controller')) {
            $loader->load('services_app_api_controllers.yaml');
        }

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

        $batch = $config['batch'];
        $container->setParameter('api_batch_record_class', $batch['record_class']);
        $container->setParameter('api_batch_max_items', $batch['max_items']);
        $container->setParameter('api_batch_retention', $batch['retention']);
    }
}
