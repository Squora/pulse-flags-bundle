<?php

declare(strict_types=1);

namespace Pulse\FlagsBundle\Tests\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Pulse\FlagsBundle\DependencyInjection\Configuration;
use Pulse\FlagsBundle\DependencyInjection\PulseFlagsExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Unit tests for PulseFlagsExtension class.
 *
 * Tests Symfony DI extension functionality including:
 * - Configuration processing and merging
 * - Service registration from services.yaml
 * - Permanent flags loading from config directory
 * - Flag validation during load
 * - Persistent storage configuration (Database only)
 * - Container parameter setting
 * - Storage type validation
 * - Alias configuration
 */
class PulseFlagsExtensionTest extends TestCase
{
    private PulseFlagsExtension $extension;
    private ContainerBuilder $container;
    private string $testDir;

    protected function setUp(): void
    {
        // Arrange: Create extension and container
        $this->extension = new PulseFlagsExtension();
        $this->container = new ContainerBuilder();

        // Create temporary directory for test flags
        $this->testDir = sys_get_temp_dir() . '/pulse_flags_ext_test_' . uniqid();
        mkdir($this->testDir . '/config/packages/pulse_flags', 0777, true);

        // Set kernel.project_dir parameter (required by extension)
        $this->container->setParameter('kernel.project_dir', $this->testDir);
    }

    protected function tearDown(): void
    {
        // Clean up temporary directory
        if (is_dir($this->testDir)) {
            $files = glob($this->testDir . '/config/packages/pulse_flags/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->testDir . '/config/packages/pulse_flags');
            rmdir($this->testDir . '/config/packages');
            rmdir($this->testDir . '/config');
            rmdir($this->testDir);
        }
    }

    public function testItReturnsCorrectAlias(): void
    {
        // Arrange: Extension created in setUp

        // Act: Get alias
        $alias = $this->extension->getAlias();

        // Assert: Returns 'pulse_flags'
        $this->assertEquals('pulse_flags', $alias);
    }

    public function testItLoadsWithDefaultDbConfiguration(): void
    {
        // Arrange: Minimal config with DB storage (default)
        $configs = [[
            'db' => [
                'dsn' => 'sqlite::memory:',
            ],
        ]];

        // Act: Load extension
        $this->extension->load($configs, $this->container);

        // Assert: DB parameters set correctly
        $this->assertTrue($this->container->hasParameter('pulse_flags.db_dsn'));
        $this->assertEquals('sqlite::memory:', $this->container->getParameter('pulse_flags.db_dsn'));
        $this->assertEquals('pulse_feature_flags', $this->container->getParameter('pulse_flags.db_table'));
    }

    public function testItConfiguresDbStorageWithAllParameters(): void
    {
        // Arrange: Full DB configuration
        $configs = [[
            'persistent_storage' => 'db',
            'db' => [
                'dsn' => 'mysql://root:secret@localhost/test',
                'table' => 'custom_flags',
            ],
        ]];

        // Act: Load extension
        $this->extension->load($configs, $this->container);

        // Assert: All DB parameters set
        $this->assertEquals('mysql://root:secret@localhost/test', $this->container->getParameter('pulse_flags.db_dsn'));
        $this->assertEquals('custom_flags', $this->container->getParameter('pulse_flags.db_table'));
        $this->assertTrue($this->container->hasAlias('pulse_flags.persistent_storage'));
    }

    public function testItUsesDefaultDbDsnWhenNotProvided(): void
    {
        // Arrange: Config without explicit DSN (uses default from Configuration)
        $configs = [[
            'persistent_storage' => 'db',
        ]];

        // Act: Load extension
        $this->extension->load($configs, $this->container);

        // Assert: Default DSN is set from Configuration class
        $this->assertTrue($this->container->hasParameter('pulse_flags.db_dsn'));
        $this->assertEquals('%env(resolve:DATABASE_URL)%', $this->container->getParameter('pulse_flags.db_dsn'));
    }

    public function testItThrowsExceptionForInvalidPersistentStorage(): void
    {
        // Arrange: Invalid storage type
        $configs = [[
            'persistent_storage' => 'invalid_storage',
            'db' => ['dsn' => 'sqlite::memory:'],
        ]];

        // Act & Assert: Symfony Config validates enum values
        $this->expectException(\Exception::class); // InvalidConfigurationException
        $this->expectExceptionMessageMatches('/invalid_storage.*not allowed/i');
        $this->extension->load($configs, $this->container);
    }

    public function testItLoadsPermanentFlagsFromYamlFiles(): void
    {
        // Arrange: Create YAML flag file
        $flagsDir = $this->testDir . '/config/packages/pulse_flags';
        file_put_contents($flagsDir . '/core.yaml', "test_flag:\n    enabled: true\n    strategy: simple\n");

        $configs = [[
            'permanent_storage' => 'yaml',
            'db' => ['dsn' => 'sqlite::memory:'],
        ]];

        // Act: Load extension
        $this->extension->load($configs, $this->container);

        // Assert: Permanent flags parameter set
        $this->assertTrue($this->container->hasParameter('pulse_flags.permanent_flags'));
        $permanentFlags = $this->container->getParameter('pulse_flags.permanent_flags');
        $this->assertArrayHasKey('core.test_flag', $permanentFlags);
        $this->assertTrue($permanentFlags['core.test_flag']['enabled']);
    }

    public function testItLoadsPermanentFlagsFromPhpFiles(): void
    {
        // Arrange: Create PHP flag file
        $flagsDir = $this->testDir . '/config/packages/pulse_flags';
        file_put_contents($flagsDir . '/flags.php', "<?php\nreturn ['test' => ['enabled' => true, 'strategy' => 'simple']];");

        $configs = [[
            'permanent_storage' => 'php',
            'db' => ['dsn' => 'sqlite::memory:'],
        ]];

        // Act: Load extension
        $this->extension->load($configs, $this->container);

        // Assert: PHP flags loaded
        $permanentFlags = $this->container->getParameter('pulse_flags.permanent_flags');
        $this->assertArrayHasKey('flags.test', $permanentFlags);
    }

    public function testItThrowsExceptionForInvalidPermanentFlag(): void
    {
        // Arrange: Create flag with invalid configuration (missing enabled field)
        $flagsDir = $this->testDir . '/config/packages/pulse_flags';
        file_put_contents($flagsDir . '/bad.yaml', "bad_flag:\n    strategy: simple\n");

        $configs = [[
            'db' => ['dsn' => 'sqlite::memory:'],
        ]];

        // Act & Assert: Validation exception thrown
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must have an "enabled" field');
        $this->extension->load($configs, $this->container);
    }

    public function testItThrowsExceptionForInvalidStrategy(): void
    {
        // Arrange: Create flag with invalid strategy
        $flagsDir = $this->testDir . '/config/packages/pulse_flags';
        file_put_contents($flagsDir . '/bad.yaml', "bad_flag:\n    enabled: true\n    strategy: invalid_strategy\n");

        $configs = [[
            'db' => ['dsn' => 'sqlite::memory:'],
        ]];

        // Act & Assert: Validation exception thrown
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid strategy');
        $this->extension->load($configs, $this->container);
    }

    public function testItThrowsExceptionForInvalidPercentage(): void
    {
        // Arrange: Create flag with percentage > 100
        $flagsDir = $this->testDir . '/config/packages/pulse_flags';
        file_put_contents($flagsDir . '/bad.yaml', "bad_flag:\n    enabled: true\n    percentage: 150\n");

        $configs = [[
            'db' => ['dsn' => 'sqlite::memory:'],
        ]];

        // Act & Assert: Validation exception thrown
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('between 0 and 100');
        $this->extension->load($configs, $this->container);
    }

    public function testItSetsAdminConfirmationParameter(): void
    {
        // Arrange: Config with admin confirmation
        $configs = [[
            'db' => ['dsn' => 'sqlite::memory:'],
            'admin' => [
                'require_confirmation' => false,
            ],
        ]];

        // Act: Load extension
        $this->extension->load($configs, $this->container);

        // Assert: Admin parameter set
        $this->assertTrue($this->container->hasParameter('pulse_flags.admin.require_confirmation'));
        $this->assertFalse($this->container->getParameter('pulse_flags.admin.require_confirmation'));
    }

    public function testItUsesDefaultAdminConfirmation(): void
    {
        // Arrange: Config without explicit admin configuration
        $configs = [[
            'db' => ['dsn' => 'sqlite::memory:'],
        ]];

        // Act: Load extension
        $this->extension->load($configs, $this->container);

        // Assert: Default value is true
        $this->assertTrue($this->container->getParameter('pulse_flags.admin.require_confirmation'));
    }

    public function testItMergesMultipleConfigSources(): void
    {
        // Arrange: Multiple config arrays (simulating multiple config files)
        $configs = [
            ['db' => ['dsn' => 'sqlite::memory:']],
            ['db' => ['table' => 'custom_flags']],
            ['admin' => ['require_confirmation' => false]],
        ];

        // Act: Load extension (should merge all configs)
        $this->extension->load($configs, $this->container);

        // Assert: All configs merged
        $this->assertTrue($this->container->hasParameter('pulse_flags.db_dsn'));
        $this->assertEquals('custom_flags', $this->container->getParameter('pulse_flags.db_table'));
        $this->assertTrue($this->container->hasParameter('pulse_flags.admin.require_confirmation'));
        $this->assertFalse($this->container->getParameter('pulse_flags.admin.require_confirmation'));
    }

    public function testItHandlesEmptyPermanentFlagsDirectory(): void
    {
        // Arrange: Empty flags directory (already created in setUp)
        $configs = [[
            'db' => ['dsn' => 'sqlite::memory:'],
        ]];

        // Act: Load extension
        $this->extension->load($configs, $this->container);

        // Assert: Empty permanent flags array
        $permanentFlags = $this->container->getParameter('pulse_flags.permanent_flags');
        $this->assertIsArray($permanentFlags);
        $this->assertEmpty($permanentFlags);
    }
}
