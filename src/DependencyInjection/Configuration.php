<?php

declare(strict_types=1);

namespace Pulse\FlagsBundle\DependencyInjection;

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

            ->arrayNode('admin')
                ->addDefaultsIfNotSet()
                ->children()
                    ->booleanNode('require_confirmation')
                        ->defaultTrue()
                        ->info('Show confirmation dialogs before modifying persistent flags in admin panel')
                    ->end()
                ->end()
            ->end()
            ->end();

        return $treeBuilder;
    }
}
