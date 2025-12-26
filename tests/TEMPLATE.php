<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Tests\{Namespace};

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
// Import your classes here

/**
 * Template for writing high-quality unit tests.
 *
 * GUIDELINES:
 * - Use AAA (Arrange-Act-Assert) structure in all tests
 * - Use data providers for parameterized tests (3+ scenarios)
 * - Use descriptive test names: it_should_*, it_can_*, it_returns_*, etc.
 * - Use self::assertSame() instead of assertEquals() for strict comparison
 * - Test edge cases: null, empty strings/arrays, boundary values
 * - Keep tests isolated (no dependencies between tests)
 * - Mock dependencies using createMock()
 * - Use named arguments for readability
 */
final class ClassNameTest extends TestCase
{
    /**
     * Basic test example with AAA structure.
     */
    #[Test]
    public function it_can_be_created_with_valid_parameters(): void
    {
        // Arrange - prepare test data and dependencies
        $dependency = $this->createMock(DependencyInterface::class);
        $parameter = 'test-value';

        // Act - execute the action being tested
        $sut = new SystemUnderTest(
            dependency: $dependency,
            parameter: $parameter
        );

        // Assert - verify the results
        self::assertInstanceOf(SystemUnderTest::class, $sut);
    }

    /**
     * Example with mock expectations.
     */
    #[Test]
    public function it_calls_dependency_method_when_executing_action(): void
    {
        // Arrange
        $dependency = $this->createMock(DependencyInterface::class);
        $dependency->expects(self::once())
            ->method('someMethod')
            ->with(self::equalTo('expected-argument'))
            ->willReturn('mocked-result');

        $sut = new SystemUnderTest($dependency);

        // Act
        $result = $sut->executeAction('expected-argument');

        // Assert
        self::assertSame('mocked-result', $result);
    }

    /**
     * Example with exception testing.
     */
    #[Test]
    public function it_throws_exception_when_invalid_parameter_provided(): void
    {
        // Arrange
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Parameter must not be empty');

        // Act & Assert (exception expectation)
        new SystemUnderTest(parameter: '');
    }

    /**
     * Example with data provider.
     */
    #[Test]
    #[DataProvider('provideValidInputsAndExpectedOutputs')]
    public function it_processes_various_inputs_correctly(
        mixed $input,
        mixed $expectedOutput,
        string $description
    ): void {
        // Arrange
        $sut = new SystemUnderTest();

        // Act
        $result = $sut->process($input);

        // Assert
        self::assertSame($expectedOutput, $result, $description);
    }

    /**
     * Data provider with descriptive scenario names.
     */
    public static function provideValidInputsAndExpectedOutputs(): iterable
    {
        yield 'empty string should return default value' => [
            'input' => '',
            'expectedOutput' => 'default',
            'description' => 'Empty input should use default',
        ];

        yield 'null value should return default value' => [
            'input' => null,
            'expectedOutput' => 'default',
            'description' => 'Null input should use default',
        ];

        yield 'valid string should be processed' => [
            'input' => 'test',
            'expectedOutput' => 'PROCESSED:test',
            'description' => 'Valid input should be processed',
        ];

        yield 'numeric value should be converted to string' => [
            'input' => 123,
            'expectedOutput' => 'PROCESSED:123',
            'description' => 'Numeric input should be converted',
        ];

        yield 'unicode string should be handled correctly' => [
            'input' => 'тест',
            'expectedOutput' => 'PROCESSED:тест',
            'description' => 'Unicode should be preserved',
        ];
    }

    /**
     * Example testing edge cases.
     */
    #[Test]
    public function it_handles_edge_cases_correctly(): void
    {
        // Arrange
        $sut = new SystemUnderTest();

        // Act & Assert - multiple edge cases in one test
        self::assertSame([], $sut->process([]), 'Empty array');
        self::assertSame(0, $sut->process(0), 'Zero value');
        self::assertFalse($sut->process(false), 'False boolean');
        self::assertNull($sut->process(null), 'Null value');
    }

    /**
     * Example testing state changes.
     */
    #[Test]
    public function it_changes_internal_state_when_method_called(): void
    {
        // Arrange
        $sut = new SystemUnderTest();
        self::assertFalse($sut->isActive(), 'Initially should be inactive');

        // Act
        $sut->activate();

        // Assert
        self::assertTrue($sut->isActive(), 'Should be active after activation');
    }

    /**
     * Example with setUp/tearDown.
     */
    private SystemUnderTest $sut;

    protected function setUp(): void
    {
        parent::setUp();
        // This runs before each test
        $this->sut = new SystemUnderTest();
    }

    protected function tearDown(): void
    {
        // This runs after each test - cleanup resources
        parent::tearDown();
    }

    /**
     * Example testing return types.
     */
    #[Test]
    public function it_returns_correct_type_for_each_method(): void
    {
        // Arrange
        $sut = new SystemUnderTest();

        // Act & Assert
        self::assertIsString($sut->getName());
        self::assertIsInt($sut->getCount());
        self::assertIsBool($sut->isValid());
        self::assertIsArray($sut->getItems());
    }

    /**
     * Example testing immutability.
     */
    #[Test]
    public function it_maintains_immutability_when_modified(): void
    {
        // Arrange
        $original = new ImmutableValueObject(value: 'original');

        // Act
        $modified = $original->withValue('modified');

        // Assert
        self::assertNotSame($original, $modified, 'Should return new instance');
        self::assertSame('original', $original->getValue(), 'Original unchanged');
        self::assertSame('modified', $modified->getValue(), 'New instance has new value');
    }

    /**
     * Example testing collections.
     */
    #[Test]
    public function it_manages_collection_correctly(): void
    {
        // Arrange
        $sut = new CollectionManager();

        // Act
        $sut->add('item1');
        $sut->add('item2');
        $sut->add('item3');

        // Assert
        self::assertCount(3, $sut->getItems());
        self::assertTrue($sut->has('item1'));
        self::assertFalse($sut->has('item4'));
        self::assertSame(['item1', 'item2', 'item3'], $sut->getItems());
    }

    /**
     * Example integration test (if needed).
     */
    #[Test]
    public function it_integrates_with_real_dependencies(): void
    {
        // Arrange - use real dependencies, not mocks
        $storage = new InMemoryStorage();
        $validator = new RealValidator();
        $sut = new SystemUnderTest(
            storage: $storage,
            validator: $validator
        );

        // Act
        $sut->save('key', 'value');
        $result = $sut->get('key');

        // Assert
        self::assertSame('value', $result);
    }

    /**
     * Example performance test (optional).
     */
    #[Test]
    public function it_processes_large_dataset_efficiently(): void
    {
        // Arrange
        $sut = new SystemUnderTest();
        $largeDataset = range(1, 10000);

        // Act
        $startTime = microtime(true);
        $sut->processBatch($largeDataset);
        $endTime = microtime(true);

        // Assert
        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        self::assertLessThan(100, $executionTime, 'Should process 10k items in <100ms');
    }
}
