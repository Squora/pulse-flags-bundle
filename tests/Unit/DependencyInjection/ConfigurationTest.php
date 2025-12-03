<?php

declare(strict_types=1);

namespace Pulse\FlagsBundle\Tests\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Pulse\FlagsBundle\DependencyInjection\Configuration;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

/**
 * Unit tests for Configuration class.
 *
 * Tests Symfony configuration tree definition including:
 * - Default values for all configuration options
 * - Enum validation for storage types (permanent_storage, persistent_storage)
 * - Database configuration structure and defaults
 * - Admin panel configuration defaults
 * - Invalid configuration rejection
 */
class ConfigurationTest extends TestCase
{
    private Configuration $configuration;
    private Processor $processor;

    protected function setUp(): void
    {
        // Arrange: Create configuration and processor
        $this->configuration = new Configuration();
        $this->processor = new Processor();
    }

    public function testItHasCorrectDefaultValues(): void
    {
        // Arrange: Empty config

        // Act: Process config
        $config = $this->processor->processConfiguration($this->configuration, []);

        // Assert: All defaults set
        $this->assertEquals('yaml', $config['permanent_storage']);
        $this->assertEquals('db', $config['persistent_storage']);
        $this->assertEquals('%env(resolve:DATABASE_URL)%', $config['db']['dsn']);
        $this->assertEquals('pulse_feature_flags', $config['db']['table']);
        $this->assertTrue($config['admin']['require_confirmation']);
    }

    public function testItAcceptsYamlPermanentStorage(): void
    {
        // Arrange: Config with YAML storage
        $configs = [['permanent_storage' => 'yaml']];

        // Act: Process
        $config = $this->processor->processConfiguration($this->configuration, $configs);

        // Assert: YAML selected
        $this->assertEquals('yaml', $config['permanent_storage']);
    }

    public function testItAcceptsPhpPermanentStorage(): void
    {
        // Arrange: Config with PHP storage
        $configs = [['permanent_storage' => 'php']];

        // Act: Process
        $config = $this->processor->processConfiguration($this->configuration, $configs);

        // Assert: PHP selected
        $this->assertEquals('php', $config['permanent_storage']);
    }

    public function testItRejectsInvalidPermanentStorage(): void
    {
        // Arrange: Invalid permanent storage value
        $configs = [['permanent_storage' => 'invalid']];

        // Act & Assert: Exception thrown
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/not allowed.*permanent_storage/i');
        $this->processor->processConfiguration($this->configuration, $configs);
    }

    public function testItAcceptsDbPersistentStorage(): void
    {
        // Arrange: Config with DB storage
        $configs = [['persistent_storage' => 'db']];

        // Act: Process
        $config = $this->processor->processConfiguration($this->configuration, $configs);

        // Assert: DB selected
        $this->assertEquals('db', $config['persistent_storage']);
    }

    public function testItRejectsInvalidPersistentStorage(): void
    {
        // Arrange: Invalid persistent storage value
        $configs = [['persistent_storage' => 'memcached']];

        // Act & Assert: Exception thrown
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/not allowed.*persistent_storage/i');
        $this->processor->processConfiguration($this->configuration, $configs);
    }

    public function testItAcceptsCustomDbConfiguration(): void
    {
        // Arrange: Full DB config
        $configs = [[
            'db' => [
                'dsn' => 'mysql://root:secret@localhost/test',
                'table' => 'custom_flags',
            ],
        ]];

        // Act: Process
        $config = $this->processor->processConfiguration($this->configuration, $configs);

        // Assert: Custom values used
        $this->assertEquals('mysql://root:secret@localhost/test', $config['db']['dsn']);
        $this->assertEquals('custom_flags', $config['db']['table']);
    }

    public function testItAcceptsAdminRequireConfirmation(): void
    {
        // Arrange: Admin with require_confirmation = false
        $configs = [[
            'admin' => [
                'require_confirmation' => false,
            ],
        ]];

        // Act: Process
        $config = $this->processor->processConfiguration($this->configuration, $configs);

        // Assert: Confirmation disabled
        $this->assertFalse($config['admin']['require_confirmation']);
    }

    public function testItMergesMultipleConfigs(): void
    {
        // Arrange: Multiple config arrays
        $configs = [
            ['permanent_storage' => 'yaml'],
            ['persistent_storage' => 'db'],
            ['db' => ['table' => 'custom_flags']],
            ['admin' => ['require_confirmation' => false]],
        ];

        // Act: Process all configs (should merge)
        $config = $this->processor->processConfiguration($this->configuration, $configs);

        // Assert: All merged correctly
        $this->assertEquals('yaml', $config['permanent_storage']);
        $this->assertEquals('db', $config['persistent_storage']);
        $this->assertEquals('custom_flags', $config['db']['table']);
        $this->assertFalse($config['admin']['require_confirmation']);
    }

    public function testItAddsDbDefaultsEvenWhenEmpty(): void
    {
        // Arrange: Empty DB config node
        $configs = [['db' => []]];

        // Act: Process
        $config = $this->processor->processConfiguration($this->configuration, $configs);

        // Assert: Defaults added
        $this->assertEquals('%env(resolve:DATABASE_URL)%', $config['db']['dsn']);
        $this->assertEquals('pulse_feature_flags', $config['db']['table']);
    }

    public function testItAddsAdminDefaultsWhenNotSpecified(): void
    {
        // Arrange: No admin config
        $configs = [[]];

        // Act: Process
        $config = $this->processor->processConfiguration($this->configuration, $configs);

        // Assert: Admin defaults added
        $this->assertTrue($config['admin']['require_confirmation']);
    }
}
