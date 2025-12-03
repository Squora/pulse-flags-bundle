<?php

declare(strict_types=1);

namespace Pulse\FlagsBundle\Tests\Unit\Storage;

use LogicException;
use PHPUnit\Framework\TestCase;
use Pulse\FlagsBundle\Storage\YamlStorage;

/**
 * Unit tests for YamlStorage class.
 *
 * Tests YAML storage functionality including:
 * - File loading and parsing
 * - Namespace handling
 * - Read-only enforcement
 * - Flag retrieval
 * - Error handling for write operations
 */
class YamlStorageTest extends TestCase
{
    private string $testDir;
    private YamlStorage $storage;

    protected function setUp(): void
    {
        // Arrange: Create temporary directory for test YAML files
        $this->testDir = sys_get_temp_dir() . '/pulse_flags_test_' . uniqid();
        mkdir($this->testDir);

        // Create test YAML files
        $this->createTestYamlFiles();

        // Create storage instance
        $this->storage = new YamlStorage($this->testDir);
    }

    protected function tearDown(): void
    {
        // Clean up: Remove test directory and files
        $files = glob($this->testDir . '/*.yaml');
        foreach ($files as $file) {
            unlink($file);
        }
        rmdir($this->testDir);
    }

    public function testItLoadsFlagsFromYamlFiles(): void
    {
        // Arrange: Files created in setUp()

        // Act: Get flag
        $config = $this->storage->get('core.feature_a');

        // Assert: Flag loaded correctly
        $this->assertIsArray($config);
        $this->assertTrue($config['enabled']);
        $this->assertEquals('simple', $config['strategy']);
    }

    public function testItNamespacesFlagsByFilename(): void
    {
        // Arrange: Files created in setUp()

        // Act: Get flags from different files
        $coreFlag = $this->storage->get('core.feature_a');
        $betaFlag = $this->storage->get('beta.experiment');

        // Assert: Both flags exist with correct namespaces
        $this->assertNotNull($coreFlag);
        $this->assertNotNull($betaFlag);
        $this->assertEquals('Core feature A', $coreFlag['description']);
        $this->assertEquals('Beta experiment', $betaFlag['description']);
    }

    public function testItReturnsNullForNonexistentFlag(): void
    {
        // Arrange: No such flag exists

        // Act: Try to get non-existent flag
        $result = $this->storage->get('nonexistent.flag');

        // Assert: Returns null
        $this->assertNull($result);
    }

    public function testItChecksFlagExistence(): void
    {
        // Arrange: Files created in setUp()

        // Act & Assert: Check existence
        $this->assertTrue($this->storage->has('core.feature_a'));
        $this->assertTrue($this->storage->has('beta.experiment'));
        $this->assertFalse($this->storage->has('nonexistent.flag'));
    }

    public function testItRetrievesAllFlags(): void
    {
        // Arrange: Files created in setUp()

        // Act: Get all flags
        $all = $this->storage->all();

        // Assert: All flags from all files retrieved
        $this->assertIsArray($all);
        $this->assertArrayHasKey('core.feature_a', $all);
        $this->assertArrayHasKey('core.feature_b', $all);
        $this->assertArrayHasKey('beta.experiment', $all);
        $this->assertCount(3, $all);
    }

    public function testItThrowsExceptionOnSetOperation(): void
    {
        // Arrange: Read-only storage

        // Act & Assert: set() throws LogicException
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('YamlStorage is read-only');
        $this->storage->set('new.flag', ['enabled' => true]);
    }

    public function testItThrowsExceptionOnRemoveOperation(): void
    {
        // Arrange: Read-only storage

        // Act & Assert: remove() throws LogicException
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('YamlStorage is read-only');
        $this->storage->remove('core.feature_a');
    }

    public function testItThrowsExceptionOnClearOperation(): void
    {
        // Arrange: Read-only storage

        // Act & Assert: clear() throws LogicException
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('YamlStorage is read-only');
        $this->storage->clear();
    }

    public function testItHandlesFlagsWithComplexConfiguration(): void
    {
        // Arrange: Flag with nested configuration created in setUp()

        // Act: Get complex flag
        $config = $this->storage->get('core.feature_b');

        // Assert: Complex structure preserved
        $this->assertIsArray($config);
        $this->assertEquals('percentage', $config['strategy']);
        $this->assertEquals(50, $config['percentage']);
        $this->assertArrayHasKey('metadata', $config);
        $this->assertEquals('team-platform', $config['metadata']['owner']);
    }

    public function testItHandlesEmptyDirectory(): void
    {
        // Arrange: Empty directory
        $emptyDir = sys_get_temp_dir() . '/pulse_flags_empty_' . uniqid();
        mkdir($emptyDir);

        // Act: Create storage with empty directory
        $storage = new YamlStorage($emptyDir);
        $all = $storage->all();

        // Assert: No flags loaded
        $this->assertIsArray($all);
        $this->assertEmpty($all);

        // Clean up
        rmdir($emptyDir);
    }

    public function testItHandlesDirectoryWithNoYamlFiles(): void
    {
        // Arrange: Directory with non-YAML files
        $testDir = sys_get_temp_dir() . '/pulse_flags_no_yaml_' . uniqid();
        mkdir($testDir);
        file_put_contents($testDir . '/readme.txt', 'Not a YAML file');

        // Act: Create storage
        $storage = new YamlStorage($testDir);
        $all = $storage->all();

        // Assert: No flags loaded
        $this->assertEmpty($all);

        // Clean up
        unlink($testDir . '/readme.txt');
        rmdir($testDir);
    }

    public function testItLoadsMultipleFlagsFromSingleFile(): void
    {
        // Arrange: core.yaml has 2 flags

        // Act: Get both flags
        $flagA = $this->storage->get('core.feature_a');
        $flagB = $this->storage->get('core.feature_b');

        // Assert: Both flags from same file loaded
        $this->assertNotNull($flagA);
        $this->assertNotNull($flagB);
        $this->assertEquals('Core feature A', $flagA['description']);
        $this->assertEquals('Core feature B', $flagB['description']);
    }

    public function testItPreservesFlagDataTypes(): void
    {
        // Arrange: Flag with various data types

        // Act: Get flag
        $config = $this->storage->get('core.feature_b');

        // Assert: Data types preserved
        $this->assertIsBool($config['enabled']);
        $this->assertIsString($config['strategy']);
        $this->assertIsInt($config['percentage']);
        $this->assertIsArray($config['metadata']);
        $this->assertIsString($config['metadata']['owner']);
    }

    public function testItHandlesFlagNamesWithUnderscoresAndDashes(): void
    {
        // Arrange: Create flag with special characters
        $yamlContent = <<<YAML
feature-with_special-chars:
    enabled: true
    description: "Special characters in name"
YAML;
        file_put_contents($this->testDir . '/special.yaml', $yamlContent);

        // Reload storage
        $storage = new YamlStorage($this->testDir);

        // Act: Get flag
        $config = $storage->get('special.feature-with_special-chars');

        // Assert: Flag loaded correctly
        $this->assertNotNull($config);
        $this->assertTrue($config['enabled']);
    }

    public function testItReturnsAllFlagsFromMultipleFiles(): void
    {
        // Arrange: Multiple files created in setUp()

        // Act: Get all flags
        $all = $this->storage->all();

        // Assert: Flags from all files included
        $coreFlags = array_filter($all, fn ($key) => str_starts_with($key, 'core.'), ARRAY_FILTER_USE_KEY);
        $betaFlags = array_filter($all, fn ($key) => str_starts_with($key, 'beta.'), ARRAY_FILTER_USE_KEY);

        $this->assertCount(2, $coreFlags);
        $this->assertCount(1, $betaFlags);
    }

    public function testItHandlesYamlWithNestedPulseFlagsKey(): void
    {
        // Arrange: YAML with pulse_flags.flags structure
        $yamlContent = <<<YAML
pulse_flags:
    flags:
        nested_flag:
            enabled: true
            description: "Nested structure"
YAML;
        file_put_contents($this->testDir . '/nested.yaml', $yamlContent);

        // Reload storage
        $storage = new YamlStorage($this->testDir);

        // Act: Get nested flag
        $config = $storage->get('nested.nested_flag');

        // Assert: Nested flag loaded correctly
        $this->assertNotNull($config);
        $this->assertTrue($config['enabled']);
        $this->assertEquals('Nested structure', $config['description']);
    }

    /**
     * Helper method to create test YAML files
     */
    private function createTestYamlFiles(): void
    {
        // core.yaml with multiple flags
        $coreYaml = <<<YAML
feature_a:
    enabled: true
    strategy: simple
    description: "Core feature A"

feature_b:
    enabled: false
    strategy: percentage
    percentage: 50
    description: "Core feature B"
    metadata:
        owner: "team-platform"
        jira: "PLAT-123"
YAML;
        file_put_contents($this->testDir . '/core.yaml', $coreYaml);

        // beta.yaml with single flag
        $betaYaml = <<<YAML
experiment:
    enabled: true
    strategy: user_id
    whitelist: [1, 2, 3]
    description: "Beta experiment"
YAML;
        file_put_contents($this->testDir . '/beta.yaml', $betaYaml);
    }
}
