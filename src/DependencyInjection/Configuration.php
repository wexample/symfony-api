<?php

namespace Wexample\SymfonyApi\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
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

        $root = $treeBuilder->getRootNode();

        $machineToken = $this->addTokenNode($root, 'machine_token', 'mt_', 600);
        $machineToken
            ->children()
            // The only roles a machine client may carry; any other refuses it.
            ->arrayNode('roles')
            ->scalarPrototype()->end()
            ->defaultValue([MachineClientInterface::ROLE])
            ->end()
            ->end();

        // A person's own tokens, for the scripts they run in their name.
        $this->addTokenNode($root, 'user_token', 'ut_', 3600);

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

        $treeBuilder->getRootNode()
            ->children()
            // Per path version (`v1`): when it was deprecated, when it stops,
            // where to read about it. Sent as response headers.
            ->arrayNode('versions')
            ->useAttributeAsKey('name')
            ->arrayPrototype()
            ->children()
            ->scalarNode('deprecation')->defaultNull()->end()
            ->scalarNode('sunset')->defaultNull()->end()
            ->scalarNode('link')->defaultNull()->end()
            ->end()
            ->end()
            ->end()
            ->end();

        $treeBuilder->getRootNode()
            ->children()
            ->arrayNode('openapi')
            ->addDefaultsIfNotSet()
            ->children()
            // Path patterns whose operations NelmioApiDocBundle documents as
            // bearer-secured, with their 401 and 429 responses.
            ->arrayNode('bearer_paths')
            ->scalarPrototype()->end()
            ->defaultValue([])
            ->end()
            ->end()
            ->end()
            ->end();

        return $treeBuilder;
    }

    /**
     * The settings every kind of token shares.
     */
    private function addTokenNode(
        ArrayNodeDefinition $root,
        string $name,
        string $prefix,
        int $clientLimit
    ): ArrayNodeDefinition {
        $node = $root->children()->arrayNode($name);

        $node
            ->addDefaultsIfNotSet()
            ->children()
            // The application's subclass of the kind's abstract token; null
            // leaves this kind unconfigured.
            ->scalarNode('token_class')
            ->defaultNull()
            ->end()
            // Readable start of every token, naming its kind in a log.
            ->scalarNode('prefix')
            ->defaultValue($prefix)
            ->end()
            // The longest a token may live, as an ISO 8601 duration; a token
            // issued without an expiration gets it. Null for no limit.
            ->scalarNode('max_lifetime')
            ->defaultNull()
            ->validate()
            ->ifTrue(function (?string $value): bool {
                if (null === $value) {
                    return false;
                }

                try {
                    new \DateInterval($value);

                    return false;
                } catch (\Exception) {
                    return true;
                }
            })
            ->thenInvalid('%s is not an ISO 8601 duration (P90D, P1Y…).')
            ->end()
            ->end()
            // Seconds between two writes of a token's last use.
            ->integerNode('last_used_interval')
            ->defaultValue(60)
            ->min(0)
            ->end()
            // A safety net, not a quota: requests per authenticated holder, and
            // failed attempts per IP — counted before any database lookup.
            ->arrayNode('rate_limit')
            ->addDefaultsIfNotSet()
            ->children()
            ->booleanNode('enabled')->defaultTrue()->end()
            ->arrayNode('client')
            ->addDefaultsIfNotSet()
            ->children()
            ->integerNode('limit')->defaultValue($clientLimit)->min(1)->end()
            ->scalarNode('interval')->defaultValue('1 hour')->end()
            ->end()
            ->end()
            ->arrayNode('ip_failures')
            ->addDefaultsIfNotSet()
            ->children()
            ->integerNode('limit')->defaultValue(30)->min(1)->end()
            ->scalarNode('interval')->defaultValue('15 minutes')->end()
            ->end()
            ->end()
            ->end()
            ->end()
            ->end();

        return $node;
    }
}
