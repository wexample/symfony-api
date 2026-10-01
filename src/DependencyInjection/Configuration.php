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

        return $treeBuilder;
    }
}
