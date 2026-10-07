<?php

namespace Wexample\SymfonyApi\DependencyInjection;

use Nelmio\ApiDocBundle\RouteDescriber\RouteDescriberInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Wexample\SymfonyApi\Interface\HealthCheckInterface;
use Wexample\SymfonyApi\OpenApi\ApiRouteDescriber;
use Wexample\SymfonyHelpers\DependencyInjection\AbstractWexampleSymfonyExtension;

class WexampleSymfonyApiExtension extends AbstractWexampleSymfonyExtension
{
    final public const string LIMITER_MACHINE_CLIENT = 'api_machine_client';

    final public const string LIMITER_MACHINE_IP_FAILURES = 'api_machine_ip_failures';

    final public const string LIMITER_USER_CLIENT = 'api_user_client';

    final public const string LIMITER_USER_IP_FAILURES = 'api_user_ip_failures';

    /**
     * The two limiters of each token firewall, declared in the framework
     * configuration from this bundle's own.
     */
    public function prepend(ContainerBuilder $container): void
    {
        parent::prepend($container);

        $config = $this->processConfiguration(
            new Configuration(),
            $container->getExtensionConfig('wexample_symfony_api')
        );
        $limiters = [];

        foreach ([
            'machine_token' => [self::LIMITER_MACHINE_CLIENT, self::LIMITER_MACHINE_IP_FAILURES],
            'user_token' => [self::LIMITER_USER_CLIENT, self::LIMITER_USER_IP_FAILURES],
        ] as $kind => [$clientLimiter, $ipLimiter]) {
            $rateLimit = $config[$kind]['rate_limit'];

            $limiters[$clientLimiter] = [
                'policy' => 'sliding_window',
                'limit' => $rateLimit['client']['limit'],
                'interval' => $rateLimit['client']['interval'],
            ];
            $limiters[$ipLimiter] = [
                'policy' => 'sliding_window',
                'limit' => $rateLimit['ip_failures']['limit'],
                'interval' => $rateLimit['ip_failures']['interval'],
            ];
        }

        $container->prependExtensionConfig('framework', [
            'rate_limiter' => $limiters,
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

        $container->registerForAutoconfiguration(HealthCheckInterface::class)
            ->addTag(HealthCheckInterface::TAG);

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

        foreach (['machine_token', 'user_token'] as $kind) {
            $token = $config[$kind];
            $container->setParameter('api_' . $kind . '_class', $token['token_class']);
            $container->setParameter('api_' . $kind . '_prefix', $token['prefix']);
            $container->setParameter('api_' . $kind . '_last_used_interval', $token['last_used_interval']);
            $container->setParameter('api_' . $kind . '_rate_limit_enabled', $token['rate_limit']['enabled']);
        }
        $container->setParameter('api_machine_token_roles', $config['machine_token']['roles']);

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
