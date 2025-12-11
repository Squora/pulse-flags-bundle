<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Configuration tree builder for PulseFlags bundle.
 *
 * Defines the configuration structure and validation rules for pulse_flags.yaml.
 * Manages settings for permanent storage format, persistent storage backend,
 * database connections, and admin panel behavior.
 *
 */
class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('pulse_flags');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
            ->enumNode('permanent_storage')
                ->values(['yaml', 'php'])
                ->defaultValue('yaml')
                ->info('Storage format for permanent (read-only) flags loaded from config files')
            ->end()
            ->enumNode('persistent_storage')
                ->values(['db'])
                ->defaultValue('db')
                ->info('Storage backend for persistent (runtime mutable) flags')
            ->end()

            ->arrayNode('db')
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('dsn')->defaultValue('%env(resolve:DATABASE_URL)%')->cannotBeEmpty()->end()
                    ->scalarNode('table')->defaultValue('pulse_feature_flags')->end()
                ->end()
            ->end()

            ->arrayNode('logging')
                ->addDefaultsIfNotSet()
                ->info('Logging configuration for feature flags operations')
                ->children()
                    ->booleanNode('enabled')
                        ->defaultTrue()
                        ->info('Enable or disable logging')
                    ->end()
                    ->scalarNode('channel')
                        ->defaultValue('pulse_flags')
                        ->info('Custom logger channel name (requires monolog configuration)')
                    ->end()
                    ->enumNode('level')
                        ->values(['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'])
                        ->defaultValue('warning')
                        ->info('Minimum log level to capture')
                    ->end()
                ->end()
            ->end()

            ->arrayNode('segments')
                ->useAttributeAsKey('name')
                ->arrayPrototype()
                    ->children()
                        ->enumNode('type')
                            ->values(['static', 'dynamic'])
                            ->defaultValue('static')
                        ->end()
                        ->arrayNode('user_ids')
                            ->scalarPrototype()->end()
                        ->end()
                        ->scalarNode('condition')->end()
                        ->scalarNode('value')->end()
                    ->end()
                    ->validate()
                        ->ifTrue(function ($v) {
                            return $v['type'] === 'static' && empty($v['user_ids']);
                        })
                        ->thenInvalid('Static segments must have user_ids array')
                    ->end()
                    ->validate()
                        ->ifTrue(function ($v) {
                            return $v['type'] === 'dynamic' && (empty($v['condition']) || !isset($v['value']));
                        })
                        ->thenInvalid('Dynamic segments must have both condition and value')
                    ->end()
                ->end()
            ->end()
            ->end();

        return $treeBuilder;
    }
}
