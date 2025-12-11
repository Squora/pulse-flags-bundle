<?php

declare(strict_types=1);

namespace Pulse\Flags\Core;

use Pulse\Flags\Core\DependencyInjection\Compiler\LoggerConfigurationPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * PulseFlags Symfony Bundle for feature flag management.
 *
 * This bundle provides comprehensive feature flag functionality for Symfony applications,
 * including multiple activation strategies, dual storage architecture, admin panel,
 * CLI commands, and Twig integration.
 *
 * Key features:
 * - Permanent (configuration-based) and persistent (runtime-editable) flags
 * - Multiple strategies: simple, percentage, user_id, date_range, composite
 * - Storage backends: MySQL, PostgreSQL, SQLite, YAML
 * - Web admin panel for flag management
 * - CLI commands for all operations
 * - Twig functions for template integration
 *
 * @see https://github.com/pulse/flags-bundle Documentation
 */
class PulseFlagsBundle extends Bundle
{
    /**
     * Build the container and register compiler passes.
     *
     * @param ContainerBuilder $container The container builder
     * @return void
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new LoggerConfigurationPass());
    }

    /**
     * Get bundle path for asset management
     *
     * This method is used by Symfony to locate public assets (CSS, JS)
     * that should be published to the public directory.
     *
     * @return string Bundle root path
     */
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
