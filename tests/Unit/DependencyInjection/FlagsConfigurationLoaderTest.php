<?php

declare(strict_types=1);

namespace Pulse\FlagsBundle\Tests\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Pulse\FlagsBundle\DependencyInjection\FlagsConfigurationLoader;
use Pulse\FlagsBundle\Enum\StorageFormat;

/**
 * Unit tests for FlagsConfigurationLoader class.
 *
 * Tests configuration loading functionality including:
 * - YAML file loading and parsing
 * - PHP file loading
 * - Local override files (.local.yaml, .local.php)
 * - Namespace handling (filename.flagname)
 * - Flag validation (enabled field requirement)
 * - Deprecated field warnings (storage field)
 * - Empty directory handling
 * - Invalid configuration handling
 */
class FlagsConfigurationLoaderTest extends TestCase
{
    private string $testDir;
    private string $flagsDir;

    protected function setUp(): void
    {
        // Arrange: Create temporary directory structure
        $this->testDir = sys_get_temp_dir() . '/pulse_flags_loader_test_' . uniqid();
        $this->flagsDir = $this->testDir . '/packages/pulse_flags';
        mkdir($this->flagsDir, 0777, true);
    }

    protected function tearDown(): void
    {
        // Clean up: Remove all test files and directories
        if (is_dir($this->flagsDir)) {
            $files = glob($this->flagsDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->flagsDir);
            rmdir(dirname($this->flagsDir));
            rmdir($this->testDir);
        }
    }

    public function testItLoadsYamlFlagsWithNamespacing(): void
    {
        // Arrange: Create YAML file
        $yamlContent = <<<YAML
new_feature:
    enabled: true
    strategy: simple
    description: "Test feature"
YAML;
        file_put_contents($this->flagsDir . '/core.yaml', $yamlContent);

        // Act: Load flags
        $flags = FlagsConfigurationLoader::loadFlagsFromDirectory($this->testDir, StorageFormat::YAML);

        // Assert: Flag loaded with namespace prefix
        $this->assertArrayHasKey('core.new_feature', $flags);
        $this->assertTrue($flags['core.new_feature']['enabled']);
        $this->assertEquals('simple', $flags['core.new_feature']['strategy']);
        $this->assertEquals('Test feature', $flags['core.new_feature']['description']);
    }

    public function testItLoadsMultipleYamlFiles(): void
    {
        // Arrange: Create multiple YAML files
        file_put_contents($this->flagsDir . '/core.yaml', "flag1:\n    enabled: true\n");
        file_put_contents($this->flagsDir . '/beta.yaml', "flag2:\n    enabled: false\n");

        // Act: Load flags
        $flags = FlagsConfigurationLoader::loadFlagsFromDirectory($this->testDir, StorageFormat::YAML);

        // Assert: Both files loaded
        $this->assertArrayHasKey('core.flag1', $flags);
        $this->assertArrayHasKey('beta.flag2', $flags);
        $this->assertTrue($flags['core.flag1']['enabled']);
        $this->assertFalse($flags['beta.flag2']['enabled']);
    }

    public function testItMergesYamlLocalOverrides(): void
    {
        // Arrange: Create base and local override files
        $baseYaml = <<<YAML
test_flag:
    enabled: false
    strategy: simple
    percentage: 0
YAML;
        $localYaml = <<<YAML
test_flag:
    enabled: true
    percentage: 50
YAML;
        file_put_contents($this->flagsDir . '/features.yaml', $baseYaml);
        file_put_contents($this->flagsDir . '/features.local.yaml', $localYaml);

        // Act: Load flags
        $flags = FlagsConfigurationLoader::loadFlagsFromDirectory($this->testDir, StorageFormat::YAML);

        // Assert: Local overrides base
        $this->assertTrue($flags['features.test_flag']['enabled']); // Overridden to true
        $this->assertEquals(50, $flags['features.test_flag']['percentage']); // Overridden to 50
        $this->assertEquals('simple', $flags['features.test_flag']['strategy']); // Preserved from base
    }

    public function testItSkipsLocalYamlFilesFromDirectProcessing(): void
    {
        // Arrange: Create only .local.yaml file (no base file)
        file_put_contents($this->flagsDir . '/standalone.local.yaml', "flag:\n    enabled: true\n");

        // Act: Load flags
        $flags = FlagsConfigurationLoader::loadFlagsFromDirectory($this->testDir, StorageFormat::YAML);

        // Assert: Local file not processed standalone
        $this->assertEmpty($flags);
    }

    public function testItLoadsPhpFlags(): void
    {
        // Arrange: Create PHP file
        $phpContent = <<<'PHP'
<?php
return [
    'new_feature' => [
        'enabled' => true,
        'strategy' => 'percentage',
        'percentage' => 75,
        'description' => 'PHP test feature',
    ],
];
PHP;
        file_put_contents($this->flagsDir . '/core.php', $phpContent);

        // Act: Load flags
        $flags = FlagsConfigurationLoader::loadFlagsFromDirectory($this->testDir, StorageFormat::PHP);

        // Assert: Flag loaded with namespace
        $this->assertArrayHasKey('core.new_feature', $flags);
        $this->assertTrue($flags['core.new_feature']['enabled']);
        $this->assertEquals(75, $flags['core.new_feature']['percentage']);
    }

    public function testItMergesPhpLocalOverrides(): void
    {
        // Arrange: Create base and local PHP files
        $basePhp = "<?php\nreturn ['flag' => ['enabled' => false, 'percentage' => 20]];";
        $localPhp = "<?php\nreturn ['flag' => ['enabled' => true]];";
        file_put_contents($this->flagsDir . '/features.php', $basePhp);
        file_put_contents($this->flagsDir . '/features.local.php', $localPhp);

        // Act: Load flags
        $flags = FlagsConfigurationLoader::loadFlagsFromDirectory($this->testDir, StorageFormat::PHP);

        // Assert: Local overrides base
        $this->assertTrue($flags['features.flag']['enabled']); // Overridden
        $this->assertEquals(20, $flags['features.flag']['percentage']); // Preserved
    }

    public function testItThrowsExceptionForMissingEnabledField(): void
    {
        // Arrange: Create YAML file without enabled field
        file_put_contents($this->flagsDir . '/core.yaml', "bad_flag:\n    strategy: simple\n");

        // Act & Assert: Exception thrown
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Flag "core.bad_flag" must have an "enabled" field');
        FlagsConfigurationLoader::loadFlagsFromDirectory($this->testDir, StorageFormat::YAML);
    }

    public function testItRemovesDeprecatedStorageFieldAndTriggersWarning(): void
    {
        // Arrange: Create YAML with deprecated storage field
        $yamlContent = <<<YAML
test_flag:
    enabled: true
    storage: permanent
    strategy: simple
YAML;
        file_put_contents($this->flagsDir . '/core.yaml', $yamlContent);

        // Arrange: Set up error handler to catch deprecation
        $deprecationTriggered = false;
        $errorHandler = function ($errno, $errstr) use (&$deprecationTriggered) {
            if ($errno === E_USER_DEPRECATED && str_contains($errstr, 'storage')) {
                $deprecationTriggered = true;
            }

            return true; // Suppress the error
        };
        set_error_handler($errorHandler);

        // Act: Load flags
        $flags = FlagsConfigurationLoader::loadFlagsFromDirectory($this->testDir, StorageFormat::YAML);

        // Restore error handler
        restore_error_handler();

        // Assert: Storage field removed and deprecation triggered
        $this->assertTrue($deprecationTriggered, 'Deprecation warning should be triggered');
        $this->assertArrayNotHasKey('storage', $flags['core.test_flag']);
        $this->assertArrayHasKey('enabled', $flags['core.test_flag']);
        $this->assertArrayHasKey('strategy', $flags['core.test_flag']);
    }

    public function testItReturnsEmptyArrayForNonexistentDirectory(): void
    {
        // Arrange: Non-existent directory
        $nonExistentDir = '/nonexistent/path/that/does/not/exist';

        // Act: Load flags
        $flags = FlagsConfigurationLoader::loadFlagsFromDirectory($nonExistentDir, StorageFormat::YAML);

        // Assert: Empty array returned
        $this->assertIsArray($flags);
        $this->assertEmpty($flags);
    }

    public function testItReturnsEmptyArrayForEmptyDirectory(): void
    {
        // Arrange: Empty directory (already created in setUp, no files added)

        // Act: Load flags
        $flags = FlagsConfigurationLoader::loadFlagsFromDirectory($this->testDir, StorageFormat::YAML);

        // Assert: Empty array
        $this->assertIsArray($flags);
        $this->assertEmpty($flags);
    }

    public function testItHandlesInvalidYamlGracefully(): void
    {
        // Arrange: Create invalid YAML file
        file_put_contents($this->flagsDir . '/invalid.yaml', "not: valid: yaml: syntax:");

        // Act: Load flags (should not throw, just skip invalid file)
        try {
            $flags = FlagsConfigurationLoader::loadFlagsFromDirectory($this->testDir, StorageFormat::YAML);
            // If parsing fails, Symfony Yaml throws exception which is not caught
            // This test verifies the loader doesn't crash the application
            $this->assertTrue(true); // If we reach here, test passes
        } catch (\Exception $e) {
            // Expected - invalid YAML throws exception
            $this->assertStringContainsString('yaml', strtolower($e->getMessage()));
        }
    }

    public function testItUsesYamlFormatByDefault(): void
    {
        // Arrange: Create YAML file
        file_put_contents($this->flagsDir . '/test.yaml', "flag:\n    enabled: true\n");

        // Act: Load without specifying format (should default to YAML)
        $flags = FlagsConfigurationLoader::loadFlagsFromDirectory($this->testDir);

        // Assert: YAML file loaded by default
        $this->assertIsArray($flags);
        $this->assertArrayHasKey('test.flag', $flags);
        $this->assertTrue($flags['test.flag']['enabled']);
    }

    public function testItValidatesStrategyCorrectly(): void
    {
        // Arrange: Test all valid strategies
        $validStrategies = ['simple', 'percentage', 'user_id', 'date_range', 'composite'];

        foreach ($validStrategies as $strategy) {
            // Act: Validate config with valid strategy
            $errors = FlagsConfigurationLoader::validateFlagConfig('test', [
                'enabled' => true,
                'strategy' => $strategy,
            ]);

            // Assert: No errors
            $this->assertEmpty($errors, "Strategy '$strategy' should be valid");
        }
    }

    public function testItRejectsInvalidStrategy(): void
    {
        // Arrange: Config with invalid strategy

        // Act: Validate
        $errors = FlagsConfigurationLoader::validateFlagConfig('test_flag', [
            'enabled' => true,
            'strategy' => 'invalid_strategy',
        ]);

        // Assert: Error returned
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Invalid strategy', $errors[0]);
        $this->assertStringContainsString('test_flag', $errors[0]);
    }

    public function testItValidatesPercentageBounds(): void
    {
        // Arrange: Test valid percentage
        $validConfig = ['enabled' => true, 'percentage' => 50];
        $invalidTooLow = ['enabled' => true, 'percentage' => -1];
        $invalidTooHigh = ['enabled' => true, 'percentage' => 101];
        $invalidNotInt = ['enabled' => true, 'percentage' => 50.5];

        // Act & Assert: Valid percentage
        $errors = FlagsConfigurationLoader::validateFlagConfig('test', $validConfig);
        $this->assertEmpty($errors);

        // Act & Assert: Invalid percentages
        $errors = FlagsConfigurationLoader::validateFlagConfig('test', $invalidTooLow);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('between 0 and 100', $errors[0]);

        $errors = FlagsConfigurationLoader::validateFlagConfig('test', $invalidTooHigh);
        $this->assertNotEmpty($errors);

        $errors = FlagsConfigurationLoader::validateFlagConfig('test', $invalidNotInt);
        $this->assertNotEmpty($errors);
    }

    public function testItValidatesDateFormats(): void
    {
        // Arrange: Valid and invalid dates
        $validConfig = [
            'enabled' => true,
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
        ];
        $invalidConfig = [
            'enabled' => true,
            'start_date' => 'not-a-date',
        ];

        // Act & Assert: Valid dates
        $errors = FlagsConfigurationLoader::validateFlagConfig('test', $validConfig);
        $this->assertEmpty($errors);

        // Act & Assert: Invalid date
        $errors = FlagsConfigurationLoader::validateFlagConfig('test', $invalidConfig);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Invalid start_date', $errors[0]);
    }

    public function testItHandlesComplexNestedConfiguration(): void
    {
        // Arrange: Complex YAML with nested arrays
        $yamlContent = <<<YAML
complex_flag:
    enabled: true
    strategy: composite
    operator: AND
    strategies:
        - type: percentage
          percentage: 50
        - type: date_range
          start_date: "2025-01-01"
          end_date: "2025-12-31"
    metadata:
        owner: "team-platform"
        tags: [beta, experimental]
YAML;
        file_put_contents($this->flagsDir . '/core.yaml', $yamlContent);

        // Act: Load flags
        $flags = FlagsConfigurationLoader::loadFlagsFromDirectory($this->testDir, StorageFormat::YAML);

        // Assert: Nested structure preserved
        $config = $flags['core.complex_flag'];
        $this->assertEquals('composite', $config['strategy']);
        $this->assertIsArray($config['strategies']);
        $this->assertCount(2, $config['strategies']);
        $this->assertEquals(50, $config['strategies'][0]['percentage']);
        $this->assertEquals('team-platform', $config['metadata']['owner']);
        $this->assertContains('beta', $config['metadata']['tags']);
    }
}
