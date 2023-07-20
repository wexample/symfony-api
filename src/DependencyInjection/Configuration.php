<?php

namespace Wexample\SymfonyApi\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

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

        return $treeBuilder;
    }
}
