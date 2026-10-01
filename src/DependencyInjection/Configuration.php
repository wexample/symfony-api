<?php

namespace Wexample\SymfonyApi\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Wexample\SymfonyApi\Interface\MachineClientInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('wexample_symfony_api');

        $treeBuilder->getRootNode()
            ->children()
            ->booleanNode('pretty_print')
            ->defaultFalse()
            ->end()
            ->end();

        $treeBuilder->getRootNode()
            ->children()
            ->integerNode('test_error_log_length')
            ->defaultValue(1000)
            ->end()
            ->end();

        $treeBuilder->getRootNode()
            ->children()
            ->arrayNode('machine_token')
            ->addDefaultsIfNotSet()
            ->children()
            // The application's subclass of AbstractMachineToken; null leaves
            // machine authentication unconfigured.
            ->scalarNode('token_class')
            ->defaultNull()
            ->end()
            // Readable start of every token, naming its kind in a log.
            ->scalarNode('prefix')
            ->defaultValue('mt_')
            ->end()
            // The only roles a machine client may carry; any other refuses it.
            ->arrayNode('roles')
            ->scalarPrototype()->end()
            ->defaultValue([MachineClientInterface::ROLE])
            ->end()
            // Seconds between two writes of a token's last use.
            ->integerNode('last_used_interval')
            ->defaultValue(60)
            ->min(0)
            ->end()
            ->end()
            ->end()
            ->end();

        $treeBuilder->getRootNode()
            ->children()
            ->arrayNode('batch')
            ->addDefaultsIfNotSet()
            ->children()
            // The application's subclass of AbstractIdempotencyRecord; null
            // leaves batch receipt unconfigured.
            ->scalarNode('record_class')
            ->defaultNull()
            ->end()
            // Items per batch beyond which the whole request is refused.
            ->integerNode('max_items')
            ->defaultValue(100)
            ->min(1)
            ->end()
            // How long a received key is remembered, as an ISO 8601 duration.
            ->scalarNode('retention')
            ->defaultValue('P30D')
            ->validate()
            ->ifTrue(function (string $value): bool {
                try {
                    new \DateInterval($value);

                    return false;
                } catch (\Exception) {
                    return true;
                }
            })
            ->thenInvalid('%s is not an ISO 8601 duration (P30D, PT12H…).')
            ->end()
            ->end()
            ->end()
            ->end()
            ->end();

        return $treeBuilder;
    }
}
