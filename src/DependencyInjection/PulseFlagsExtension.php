<?php

declare(strict_types=1);

namespace Pulse\FlagsBundle\DependencyInjection;

use Exception;
use InvalidArgumentException;
use Pulse\FlagsBundle\Enum\StorageFormat;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * Dependency Injection extension for PulseFlags bundle.
 *
 * Handles bundle configuration processing, service registration, and storage
 * backend setup. Loads permanent flags from configuration files at container
 * compile time and configures the appropriate persistent storage backend.
 *
 * Responsibilities:
 * - Load and validate bundle configuration
 * - Register bundle services from services.yaml
 * - Load permanent flags from config/packages/pulse_flags/*.{yaml,php}
 * - Configure persistent storage (DB)
 * - Set bundle parameters for runtime use
 *
 * Storage configuration:
 * - DB: Creates PDO-based storage with database-specific SQL
 */
class PulseFlagsExtension extends Extension implements PrependExtensionInterface
{
    /**
     * Prepend configuration to register Twig paths.
     *
     * @param ContainerBuilder $container The dependency injection container
     * @return void
     */
    public function prepend(ContainerBuilder $container): void
    {
        // Register Twig namespace paths for admin templates
        $container->prependExtensionConfig('twig', [
            'paths' => [
                __DIR__ . '/../../templates/admin' => 'PulseFlagsAdmin',
            ],
        ]);
    }
    /**
     * Loads bundle configuration and registers services.
     *
     * Processes configuration, loads services, validates permanent flags,
     * and configures the selected persistent storage backend.
     *
     * @param array<int, array<string, mixed>> $configs Bundle configuration from all config files
     * @param ContainerBuilder $container The dependency injection container
     * @return void
     * @throws InvalidArgumentException|Exception If configuration is invalid
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../../config')
        );
        $loader->load('services.yaml');

        $projectDir = $container->getParameter('kernel.project_dir');
        $configDir = (string) $projectDir . '/config';
        $permanentFormatValue = $config['permanent_storage'] ?? 'yaml';
        $permanentFormat = StorageFormat::tryFrom($permanentFormatValue) ?? StorageFormat::YAML;
        $permanentFlags = FlagsConfigurationLoader::loadFlagsFromDirectory($configDir, $permanentFormat);

        foreach ($permanentFlags as $name => $flagConfig) {
            $errors = FlagsConfigurationLoader::validateFlagConfig($name, $flagConfig);
            if (!empty($errors)) {
                throw new \InvalidArgumentException(implode("\n", $errors));
            }
        }

        $this->configurePersistentStorage($container, $config);

        $container->setParameter('pulse_flags.permanent_flags', $permanentFlags);
        $container->setParameter(
            'pulse_flags.admin.require_confirmation',
            $config['admin']['require_confirmation'] ?? true,
        );
    }

    /**
     * Configures the persistent storage backend based on configuration.
     *
     * Sets up container parameters and service aliases for database storage.
     * Validates required configuration options.
     *
     * @param ContainerBuilder $container The dependency injection container
     * @param array<string, mixed> $config Processed bundle configuration
     * @return void
     * @throws \InvalidArgumentException If required configuration is missing
     */
    private function configurePersistentStorage(ContainerBuilder $container, array $config): void
    {
        $dbConfig = $config['db'] ?? [];
        if (empty($dbConfig['dsn'])) {
            throw new \InvalidArgumentException('Database DSN must be configured for persistent storage');
        }
        $container->setParameter('pulse_flags.db_dsn', $dbConfig['dsn']);
        $container->setParameter('pulse_flags.db_table', $dbConfig['table'] ?? 'pulse_feature_flags');
        $container->setAlias('pulse_flags.persistent_storage', 'pulse_flags.persistent_storage.db');
    }

    /**
     * Returns the bundle's configuration alias.
     *
     * Allows using "pulse_flags:" instead of "pulse_flags_bundle:" in configuration files.
     *
     * @return string The configuration alias 'pulse_flags'
     */
    public function getAlias(): string
    {
        return 'pulse_flags';
    }
}
