<?php

namespace Wexample\SymfonyApi\DependencyInjection;

use Nelmio\ApiDocBundle\NelmioApiDocBundle;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Wexample\SymfonyActivity\WexampleSymfonyActivityBundle;
use Wexample\SymfonyApi\EventSubscriber\ApiTokenActivitySubscriber;
use Wexample\SymfonyApi\Interface\ApiDocumentationSectionInterface;
use Wexample\SymfonyApi\Interface\HealthCheckInterface;
use Wexample\SymfonyApi\OpenApi\ApiRouteDescriber;
use Wexample\SymfonyApi\Service\ApiRateLimitPlanService;
use Wexample\SymfonyApi\Service\UserTokenPasswordLoginService;
use Wexample\SymfonyHelpers\DependencyInjection\AbstractWexampleSymfonyExtension;

class WexampleSymfonyApiExtension extends AbstractWexampleSymfonyExtension
{
    final public const string LIMITER_MACHINE_CLIENT = 'api_machine_client';

    final public const string LIMITER_MACHINE_IP_FAILURES = 'api_machine_ip_failures';

    final public const string LIMITER_USER_CLIENT = 'api_user_client';

    final public const string LIMITER_USER_IP_FAILURES = 'api_user_ip_failures';

    final public const string LIMITER_USER_PASSWORD_LOGIN = 'api_user_password_login';

    final public const string LIMITER_ANONYMOUS = 'api_anonymous';

    /** Followed by the plan's name. */
    final public const string LIMITER_PLAN_PREFIX = 'api_plan_';

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

            $client = [
                'policy' => 'sliding_window',
                'limit' => $rateLimit['client']['limit'],
                'interval' => $rateLimit['client']['interval'],
            ];

            // With a burst window, the client's limiter is both: a request
            // passes when neither is spent, and waits for the later of the two.
            if ($rateLimit['burst']['enabled']) {
                $limiters[$clientLimiter . '_allowance'] = $client;
                $limiters[$clientLimiter . '_burst'] = [
                    'policy' => 'sliding_window',
                    'limit' => $rateLimit['burst']['limit'],
                    'interval' => $rateLimit['burst']['interval'],
                ];
                $client = [
                    'policy' => 'compound',
                    'limiters' => [$clientLimiter . '_allowance', $clientLimiter . '_burst'],
                ];
            }

            $limiters[$clientLimiter] = $client;
            $limiters[$ipLimiter] = [
                'policy' => 'sliding_window',
                'limit' => $rateLimit['ip_failures']['limit'],
                'interval' => $rateLimit['ip_failures']['interval'],
            ];
        }

        $passwordLogin = $config['user_token']['password_login'];
        $limiters[self::LIMITER_USER_PASSWORD_LOGIN] = [
            'policy' => 'sliding_window',
            'limit' => $passwordLogin['rate_limit']['limit'],
            'interval' => $passwordLogin['rate_limit']['interval'],
        ];

        $limiters = [...$limiters, ...self::windowLimiters(self::LIMITER_ANONYMOUS, $config['anonymous_rate_limit']['windows'])];

        foreach ($config['rate_limit_plans'] as $plan => $windows) {
            $limiters = [...$limiters, ...self::windowLimiters(self::LIMITER_PLAN_PREFIX . $plan, $windows)];
        }

        $container->prependExtensionConfig('framework', [
            'rate_limiter' => $limiters,
        ]);
    }

    /**
     * A limiter for one window, a compound of one per window for several: a
     * call passes when none is spent.
     *
     * @param list<array{limit: int, interval: string}> $windows
     * @return array<string, array<string, mixed>>
     */
    private static function windowLimiters(string $name, array $windows): array
    {
        $limiters = [];

        foreach (array_values($windows) as $index => $window) {
            $limiters[$name . '_' . $index] = ['policy' => 'sliding_window', 'limit' => $window['limit'], 'interval' => $window['interval']];
        }

        return 1 === count($limiters)
            ? [$name => reset($limiters)]
            : $limiters + [$name => ['policy' => 'compound', 'limiters' => array_keys($limiters)]];
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
        $container->registerForAutoconfiguration(ApiDocumentationSectionInterface::class)
            ->addTag(ApiDocumentationSectionInterface::TAG);

        // The token facts in the holders' histories, where there are histories.
        if (in_array(WexampleSymfonyActivityBundle::class, $container->getParameter('kernel.bundles'), true)) {
            $container->autowire(ApiTokenActivitySubscriber::class)->setAutoconfigured(true);
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

        foreach (['machine_token', 'user_token'] as $kind) {
            $token = $config[$kind];
            $container->setParameter('api_' . $kind . '_class', $token['token_class']);
            $container->setParameter('api_' . $kind . '_prefix', $token['prefix']);
            $container->setParameter('api_' . $kind . '_last_used_interval', $token['last_used_interval']);
            $container->setParameter('api_' . $kind . '_max_lifetime', $token['max_lifetime']);
            $container->setParameter('api_' . $kind . '_rate_limit_enabled', $token['rate_limit']['enabled']);
        }
        $container->setParameter('api_machine_token_roles', $config['machine_token']['roles']);

        $anonymous = $config['anonymous_rate_limit'];
        $container->setParameter('api_anonymous_rate_limit_enabled', $anonymous['enabled'] && [] !== $anonymous['windows']);
        $container->setParameter('api_anonymous_rate_limit_paths', $anonymous['paths']);
        $container->setParameter('api_anonymous_rate_limit_excluded_routes', $anonymous['excluded_routes']);

        $plans = [];
        foreach (array_keys($config['rate_limit_plans']) as $plan) {
            $plans[$plan] = new Reference('limiter.' . self::LIMITER_PLAN_PREFIX . $plan);
        }
        $container->getDefinition(ApiRateLimitPlanService::class)
            ->setArgument('$limiters', ServiceLocatorTagPass::register($container, $plans));

        // A password login, only where the application names the provider.
        $passwordLogin = $config['user_token']['password_login'];
        if (null !== $passwordLogin['provider']) {
            $container->register(UserTokenPasswordLoginService::class, UserTokenPasswordLoginService::class)
                ->setAutowired(true)
                ->setPublic(true)
                ->setArgument('$userProvider', new Reference('security.user.provider.concrete.' . $passwordLogin['provider']))
                ->setArgument('$loginLimiter', new Reference('limiter.' . self::LIMITER_USER_PASSWORD_LOGIN))
                ->setArgument('$userChecker', null === $passwordLogin['user_checker'] ? null : new Reference($passwordLogin['user_checker']))
                ->setArgument('$lifetime', $passwordLogin['lifetime'])
                ->setArgument('$label', $passwordLogin['label']);
        }

        $batch = $config['batch'];
        $container->setParameter('api_documentation', $config['documentation']);
        $container->setParameter('api_batch_record_class', $batch['record_class']);
        $container->setParameter('api_batch_max_items', $batch['max_items']);
        $container->setParameter('api_batch_retention', $batch['retention']);

        $container->setParameter('api_versions', $config['versions']);
        $container->setParameter('api_openapi_bearer_paths', $config['openapi']['bearer_paths']);

        // The documentation bridge: what this package's attributes mean to
        // NelmioApiDocBundle — a dependency, an api having its documentation —,
        // once the application registers it.
        if (in_array(NelmioApiDocBundle::class, $container->getParameter('kernel.bundles'), true)) {
            $container->register(ApiRouteDescriber::class, ApiRouteDescriber::class)
                ->setAutowired(true)
                ->addTag('nelmio_api_doc.route_describer', ['priority' => -300]);
        }
    }
}
