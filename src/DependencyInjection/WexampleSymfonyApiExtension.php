<?php

namespace Wexample\SymfonyApi\DependencyInjection;

use Nelmio\ApiDocBundle\RouteDescriber\RouteDescriberInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Wexample\SymfonyApi\OpenApi\ApiRouteDescriber;
use Wexample\SymfonyHelpers\DependencyInjection\AbstractWexampleSymfonyExtension;

class WexampleSymfonyApiExtension extends AbstractWexampleSymfonyExtension
{
    final public const string LIMITER_MACHINE_CLIENT = 'api_machine_client';

    final public const string LIMITER_MACHINE_IP_FAILURES = 'api_machine_ip_failures';

    /**
     * The two limiters of the machine firewall, declared in the framework
     * configuration from this bundle's own.
     */
    public function prepend(ContainerBuilder $container): void
    {
        parent::prepend($container);

        $config = $this->processConfiguration(
            new Configuration(),
            $container->getExtensionConfig('wexample_symfony_api')
        );
        $rateLimit = $config['machine_token']['rate_limit'];

        $container->prependExtensionConfig('framework', [
            'rate_limiter' => [
                self::LIMITER_MACHINE_CLIENT => [
                    'policy' => 'sliding_window',
                    'limit' => $rateLimit['client']['limit'],
                    'interval' => $rateLimit['client']['interval'],
                ],
                self::LIMITER_MACHINE_IP_FAILURES => [
                    'policy' => 'sliding_window',
                    'limit' => $rateLimit['ip_failures']['limit'],
                    'interval' => $rateLimit['ip_failures']['interval'],
                ],
            ],
        ]);
    }

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
        $container->setParameter('api_machine_token_rate_limit_enabled', $machineToken['rate_limit']['enabled']);

        $batch = $config['batch'];
        $container->setParameter('api_batch_record_class', $batch['record_class']);
        $container->setParameter('api_batch_max_items', $batch['max_items']);
        $container->setParameter('api_batch_retention', $batch['retention']);

        $container->setParameter('api_versions', $config['versions']);
        $container->setParameter('api_openapi_bearer_paths', $config['openapi']['bearer_paths']);

        // The documentation bridge, only where NelmioApiDocBundle is installed.
        if (interface_exists(RouteDescriberInterface::class)) {
            $container->register(ApiRouteDescriber::class, ApiRouteDescriber::class)
                ->setAutowired(true)
                ->addTag('nelmio_api_doc.route_describer', ['priority' => -300]);
        }
    }
}
