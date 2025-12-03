<?php

declare(strict_types=1);

namespace Pulse\FlagsBundle\Tests\Unit\Storage;

use PHPUnit\Framework\TestCase;
use Pulse\FlagsBundle\Storage\DbStorage;

/**
 * Unit tests for DbStorage class.
 *
 * Tests database storage functionality including:
 * - DSN parsing (Doctrine format to PDO format)
 * - Multi-database support (MySQL, PostgreSQL, SQLite)
 * - CRUD operations
 * - Table initialization
 * - JSON encoding/decoding
 * - Error handling
 */
class DbStorageTest extends TestCase
{
    private DbStorage $storage;
    private string $sqliteDb;

    protected function setUp(): void
    {
        // Arrange: Create in-memory SQLite database for testing
        $this->sqliteDb = ':memory:';
        $this->storage = new DbStorage(
            'sqlite::memory:',
            null,
            null,
            'test_feature_flags'
        );
        $this->storage->initializeTable();
    }

    protected function tearDown(): void
    {
        // Clean up: Clear all data after each test
        if ($this->storage) {
            $this->storage->clear();
        }
    }

    public function testItInitializesTableSuccessfully(): void
    {
        // Arrange: Fresh storage instance
        $storage = new DbStorage('sqlite::memory:', null, null, 'flags_init_test');

        // Act: Initialize the table
        $storage->initializeTable();
        $pdo = $storage->getPdo();
        $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='flags_init_test'");

        // Assert: Table exists
        $this->assertNotEmpty($stmt->fetchAll());
    }

    public function testItStoresAndRetrievesFlagConfiguration(): void
    {
        // Arrange: Prepare test data
        $flagName = 'test_feature';
        $config = [
            'enabled' => true,
            'strategy' => 'percentage',
            'percentage' => 50,
        ];

        // Act: Store the flag
        $this->storage->set($flagName, $config);
        $retrieved = $this->storage->get($flagName);

        // Assert: Retrieved config matches stored config
        $this->assertEquals($config, $retrieved);
    }

    public function testItReturnsNullForNonexistentFlag(): void
    {
        // Arrange: Empty storage (no flags)

        // Act: Try to get non-existent flag
        $result = $this->storage->get('nonexistent_flag');

        // Assert: Returns null
        $this->assertNull($result);
    }

    public function testItUpdatesExistingFlagConfiguration(): void
    {
        // Arrange: Store initial configuration
        $flagName = 'update_test';
        $initialConfig = ['enabled' => false, 'strategy' => 'simple'];
        $this->storage->set($flagName, $initialConfig);

        // Act: Update the configuration
        $updatedConfig = ['enabled' => true, 'strategy' => 'percentage', 'percentage' => 75];
        $this->storage->set($flagName, $updatedConfig);
        $retrieved = $this->storage->get($flagName);

        // Assert: Configuration was updated
        $this->assertEquals($updatedConfig, $retrieved);
        $this->assertTrue($retrieved['enabled']);
        $this->assertEquals(75, $retrieved['percentage']);
    }

    public function testItRemovesFlagFromStorage(): void
    {
        // Arrange: Store a flag
        $flagName = 'remove_test';
        $this->storage->set($flagName, ['enabled' => true]);

        // Act: Remove the flag
        $this->storage->remove($flagName);

        // Assert: Flag no longer exists
        $this->assertFalse($this->storage->has($flagName));
        $this->assertNull($this->storage->get($flagName));
    }

    public function testItChecksFlagExistence(): void
    {
        // Arrange: Store a flag
        $flagName = 'existence_test';
        $this->storage->set($flagName, ['enabled' => true]);

        // Act & Assert: Check existence
        $this->assertTrue($this->storage->has($flagName));
        $this->assertFalse($this->storage->has('nonexistent'));
    }

    public function testItRetrievesAllFlags(): void
    {
        // Arrange: Store multiple flags
        $this->storage->set('flag1', ['enabled' => true, 'strategy' => 'simple']);
        $this->storage->set('flag2', ['enabled' => false, 'strategy' => 'percentage', 'percentage' => 25]);
        $this->storage->set('flag3', ['enabled' => true, 'strategy' => 'user_id', 'whitelist' => [1, 2, 3]]);

        // Act: Get all flags
        $all = $this->storage->all();

        // Assert: All flags are retrieved
        $this->assertCount(3, $all);
        $this->assertArrayHasKey('flag1', $all);
        $this->assertArrayHasKey('flag2', $all);
        $this->assertArrayHasKey('flag3', $all);
        $this->assertTrue($all['flag1']['enabled']);
        $this->assertEquals(25, $all['flag2']['percentage']);
        $this->assertEquals([1, 2, 3], $all['flag3']['whitelist']);
    }

    public function testItReturnsEmptyArrayWhenNoFlagsExist(): void
    {
        // Arrange: Empty storage

        // Act: Get all flags
        $all = $this->storage->all();

        // Assert: Empty array returned
        $this->assertIsArray($all);
        $this->assertEmpty($all);
    }

    public function testItClearsAllFlags(): void
    {
        // Arrange: Store multiple flags
        $this->storage->set('flag1', ['enabled' => true]);
        $this->storage->set('flag2', ['enabled' => false]);
        $this->storage->set('flag3', ['enabled' => true]);

        // Act: Clear all flags
        $this->storage->clear();

        // Assert: No flags remain
        $this->assertEmpty($this->storage->all());
        $this->assertFalse($this->storage->has('flag1'));
        $this->assertFalse($this->storage->has('flag2'));
        $this->assertFalse($this->storage->has('flag3'));
    }

    public function testItHandlesComplexConfigurationWithNestedArrays(): void
    {
        // Arrange: Complex nested configuration
        $flagName = 'complex_flag';
        $config = [
            'enabled' => true,
            'strategy' => 'composite',
            'operator' => 'AND',
            'strategies' => [
                ['type' => 'percentage', 'percentage' => 50],
                ['type' => 'date_range', 'start_date' => '2025-01-01', 'end_date' => '2025-12-31'],
            ],
            'metadata' => [
                'owner' => 'team-platform',
                'jira_ticket' => 'PLAT-1234',
            ],
        ];

        // Act: Store and retrieve
        $this->storage->set($flagName, $config);
        $retrieved = $this->storage->get($flagName);

        // Assert: Complex structure preserved
        $this->assertEquals($config, $retrieved);
        $this->assertCount(2, $retrieved['strategies']);
        $this->assertEquals('team-platform', $retrieved['metadata']['owner']);
    }

    public function testItHandlesUnicodeCharactersInConfiguration(): void
    {
        // Arrange: Config with Unicode characters
        $flagName = 'unicode_test';
        $config = [
            'enabled' => true,
            'description' => 'Test with émojis 🎉 and Cyrillic текст',
            'strategy' => 'simple',
        ];

        // Act: Store and retrieve
        $this->storage->set($flagName, $config);
        $retrieved = $this->storage->get($flagName);

        // Assert: Unicode preserved correctly
        $this->assertEquals($config, $retrieved);
        $this->assertStringContainsString('🎉', $retrieved['description']);
        $this->assertStringContainsString('текст', $retrieved['description']);
    }

    public function testItParsesDoctrineDsnFormatCorrectly(): void
    {
        // Arrange & Act: Create storage with Doctrine DSN format
        $storage = new DbStorage(
            'mysql://root:password@localhost:3306/test_db',
            null,
            null,
            'flags'
        );

        // Assert: Driver is extracted correctly
        $this->assertEquals('mysql', $storage->getDriver());
    }

    public function testItHandlesPdoDsnFormat(): void
    {
        // Arrange & Act: Create storage with PDO DSN format
        $storage = new DbStorage(
            'sqlite::memory:',
            null,
            null,
            'flags'
        );

        // Assert: Driver is extracted correctly
        $this->assertEquals('sqlite', $storage->getDriver());
    }

    public function testItDetectsSqliteDriver(): void
    {
        // Arrange & Act: SQLite storage
        $storage = new DbStorage('sqlite::memory:');

        // Assert: Driver detected
        $this->assertEquals('sqlite', $storage->getDriver());
    }

    public function testItDetectsMysqlDriver(): void
    {
        // Arrange & Act: MySQL DSN
        $storage = new DbStorage('mysql://localhost/test');

        // Assert: Driver detected
        $this->assertEquals('mysql', $storage->getDriver());
    }

    public function testItDetectsPostgresqlDriver(): void
    {
        // Arrange & Act: PostgreSQL DSN
        $storage = new DbStorage('pgsql://localhost/test');

        // Assert: Driver detected
        $this->assertEquals('pgsql', $storage->getDriver());
    }

    public function testItThrowsExceptionForInvalidJsonInDatabase(): void
    {
        // Arrange: Insert invalid JSON directly into database
        $pdo = $this->storage->getPdo();
        $pdo->exec("INSERT INTO test_feature_flags (name, config) VALUES ('invalid_json', 'not-valid-json')");

        // Act & Assert: Exception thrown on retrieval
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to decode JSON');
        $this->storage->get('invalid_json');
    }

    public function testItPreservesBooleanTypes(): void
    {
        // Arrange: Configuration with booleans
        $config = [
            'enabled' => true,
            'internal_only' => false,
            'beta' => true,
        ];

        // Act: Store and retrieve
        $this->storage->set('bool_test', $config);
        $retrieved = $this->storage->get('bool_test');

        // Assert: Boolean types preserved
        $this->assertIsBool($retrieved['enabled']);
        $this->assertIsBool($retrieved['internal_only']);
        $this->assertTrue($retrieved['enabled']);
        $this->assertFalse($retrieved['internal_only']);
    }

    public function testItPreservesIntegerTypes(): void
    {
        // Arrange: Configuration with integers
        $config = [
            'enabled' => true,
            'percentage' => 50,
            'max_users' => 1000,
            'priority' => 0,
        ];

        // Act: Store and retrieve
        $this->storage->set('int_test', $config);
        $retrieved = $this->storage->get('int_test');

        // Assert: Integer types preserved
        $this->assertIsInt($retrieved['percentage']);
        $this->assertIsInt($retrieved['max_users']);
        $this->assertIsInt($retrieved['priority']);
        $this->assertEquals(50, $retrieved['percentage']);
        $this->assertEquals(0, $retrieved['priority']);
    }

    public function testItHandlesEmptyConfigurationArray(): void
    {
        // Arrange: Empty config
        $config = [];

        // Act: Store and retrieve
        $this->storage->set('empty_test', $config);
        $retrieved = $this->storage->get('empty_test');

        // Assert: Empty array preserved
        $this->assertIsArray($retrieved);
        $this->assertEmpty($retrieved);
    }

    public function testItHandlesFlagNamesWithSpecialCharacters(): void
    {
        // Arrange: Flag names with dots, underscores, dashes
        $flags = [
            'feature.new-ui' => ['enabled' => true],
            'beta_test-123' => ['enabled' => false],
            'core.payment.v2' => ['enabled' => true],
        ];

        // Act: Store all flags
        foreach ($flags as $name => $config) {
            $this->storage->set($name, $config);
        }

        // Assert: All flags can be retrieved
        foreach ($flags as $name => $config) {
            $this->assertTrue($this->storage->has($name));
            $this->assertEquals($config, $this->storage->get($name));
        }
    }

    public function testItReturnsFlagsInAlphabeticalOrder(): void
    {
        // Arrange: Store flags in random order
        $this->storage->set('zebra_flag', ['enabled' => true]);
        $this->storage->set('alpha_flag', ['enabled' => true]);
        $this->storage->set('beta_flag', ['enabled' => true]);

        // Act: Get all flags
        $all = $this->storage->all();
        $names = array_keys($all);

        // Assert: Names are in alphabetical order
        $this->assertEquals(['alpha_flag', 'beta_flag', 'zebra_flag'], $names);
    }

    public function testItHandlesRapidUpdatesToSameFlag(): void
    {
        // Arrange: Flag name
        $flagName = 'rapid_update_test';

        // Act: Perform multiple rapid updates
        for ($i = 0; $i < 10; $i++) {
            $this->storage->set($flagName, ['enabled' => true, 'version' => $i]);
        }
        $retrieved = $this->storage->get($flagName);

        // Assert: Latest version is stored
        $this->assertEquals(9, $retrieved['version']);
    }

    public function testItAllowsRemovalOfNonexistentFlagWithoutError(): void
    {
        // Arrange: No flag exists

        // Act: Remove non-existent flag (should not throw)
        $this->storage->remove('does_not_exist');

        // Assert: No exception thrown (test passes if we reach here)
        $this->assertTrue(true);
    }
}
