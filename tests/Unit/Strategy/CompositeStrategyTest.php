<?php

declare(strict_types=1);

namespace Pulse\FlagsBundle\Tests\Unit\Strategy;

use PHPUnit\Framework\TestCase;
use Pulse\FlagsBundle\Strategy\CompositeStrategy;
use Pulse\FlagsBundle\Strategy\StrategyInterface;

/**
 * Unit tests for CompositeStrategy class.
 *
 * Tests composite strategy functionality including:
 * - AND operator logic
 * - OR operator logic
 * - Multiple strategy combination
 * - Strategy registration
 * - Empty strategies handling
 * - Unknown strategy handling
 * - Short-circuit evaluation
 */
class CompositeStrategyTest extends TestCase
{
    private CompositeStrategy $strategy;

    protected function setUp(): void
    {
        // Arrange: Create fresh composite strategy for each test
        $this->strategy = new CompositeStrategy();
    }

    public function testItReturnsCorrectStrategyName(): void
    {
        // Arrange & Act: Get strategy name
        $name = $this->strategy->getName();

        // Assert: Returns 'composite'
        $this->assertEquals('composite', $name);
    }

    public function testItReturnsTrueForEmptyStrategiesArray(): void
    {
        // Arrange: Empty strategies configuration
        $config = [
            'strategies' => [],
        ];

        // Act: Evaluate
        $result = $this->strategy->isEnabled($config);

        // Assert: Returns true for empty array
        $this->assertTrue($result);
    }

    public function testItReturnsTrueWhenNoStrategiesKeyProvided(): void
    {
        // Arrange: Config without strategies key
        $config = ['operator' => 'AND'];

        // Act: Evaluate
        $result = $this->strategy->isEnabled($config);

        // Assert: Returns true
        $this->assertTrue($result);
    }

    public function testItUsesAndOperatorByDefault(): void
    {
        // Arrange: Two mock strategies, both return true
        $mockStrategy1 = $this->createMockStrategy('strategy1', true);
        $mockStrategy2 = $this->createMockStrategy('strategy2', true);

        $this->strategy->addStrategy($mockStrategy1);
        $this->strategy->addStrategy($mockStrategy2);

        $config = [
            'strategies' => [
                ['type' => 'strategy1'],
                ['type' => 'strategy2'],
            ],
            // No operator specified - should default to AND
        ];

        // Act: Evaluate
        $result = $this->strategy->isEnabled($config);

        // Assert: Both strategies evaluated, result is true
        $this->assertTrue($result);
    }

    public function testItReturnsTrueWhenAllStrategiesPassWithAndOperator(): void
    {
        // Arrange: Three strategies, all return true
        $mockStrategy1 = $this->createMockStrategy('strategy1', true);
        $mockStrategy2 = $this->createMockStrategy('strategy2', true);
        $mockStrategy3 = $this->createMockStrategy('strategy3', true);

        $this->strategy->addStrategy($mockStrategy1);
        $this->strategy->addStrategy($mockStrategy2);
        $this->strategy->addStrategy($mockStrategy3);

        $config = [
            'operator' => 'AND',
            'strategies' => [
                ['type' => 'strategy1'],
                ['type' => 'strategy2'],
                ['type' => 'strategy3'],
            ],
        ];

        // Act: Evaluate
        $result = $this->strategy->isEnabled($config);

        // Assert: All pass, result is true
        $this->assertTrue($result);
    }

    public function testItReturnsFalseWhenAnyStrategyFailsWithAndOperator(): void
    {
        // Arrange: Three strategies, middle one fails
        $mockStrategy1 = $this->createMockStrategy('strategy1', true);
        $mockStrategy2 = $this->createMockStrategy('strategy2', false); // Fails
        $mockStrategy3 = $this->createMockStrategy('strategy3', true);

        $this->strategy->addStrategy($mockStrategy1);
        $this->strategy->addStrategy($mockStrategy2);
        $this->strategy->addStrategy($mockStrategy3);

        $config = [
            'operator' => 'AND',
            'strategies' => [
                ['type' => 'strategy1'],
                ['type' => 'strategy2'],
                ['type' => 'strategy3'],
            ],
        ];

        // Act: Evaluate
        $result = $this->strategy->isEnabled($config);

        // Assert: One failed, result is false
        $this->assertFalse($result);
    }

    public function testItShortCircuitsOnFirstFailureWithAndOperator(): void
    {
        // Arrange: Two strategies, first fails
        $mockStrategy1 = $this->createMock(StrategyInterface::class);
        $mockStrategy1->method('getName')->willReturn('strategy1');
        $mockStrategy1->expects($this->once()) // Should be called
            ->method('isEnabled')
            ->willReturn(false);

        $mockStrategy2 = $this->createMock(StrategyInterface::class);
        $mockStrategy2->method('getName')->willReturn('strategy2');
        $mockStrategy2->expects($this->never()) // Should NOT be called (short-circuit)
            ->method('isEnabled');

        $this->strategy->addStrategy($mockStrategy1);
        $this->strategy->addStrategy($mockStrategy2);

        $config = [
            'operator' => 'AND',
            'strategies' => [
                ['type' => 'strategy1'],
                ['type' => 'strategy2'],
            ],
        ];

        // Act: Evaluate
        $result = $this->strategy->isEnabled($config);

        // Assert: Short-circuited, result is false
        $this->assertFalse($result);
    }

    public function testItReturnsTrueWhenAnyStrategyPassesWithOrOperator(): void
    {
        // Arrange: Three strategies, middle one passes
        $mockStrategy1 = $this->createMockStrategy('strategy1', false);
        $mockStrategy2 = $this->createMockStrategy('strategy2', true); // Passes
        $mockStrategy3 = $this->createMockStrategy('strategy3', false);

        $this->strategy->addStrategy($mockStrategy1);
        $this->strategy->addStrategy($mockStrategy2);
        $this->strategy->addStrategy($mockStrategy3);

        $config = [
            'operator' => 'OR',
            'strategies' => [
                ['type' => 'strategy1'],
                ['type' => 'strategy2'],
                ['type' => 'strategy3'],
            ],
        ];

        // Act: Evaluate
        $result = $this->strategy->isEnabled($config);

        // Assert: One passed, result is true
        $this->assertTrue($result);
    }

    public function testItReturnsFalseWhenAllStrategiesFailWithOrOperator(): void
    {
        // Arrange: Three strategies, all fail
        $mockStrategy1 = $this->createMockStrategy('strategy1', false);
        $mockStrategy2 = $this->createMockStrategy('strategy2', false);
        $mockStrategy3 = $this->createMockStrategy('strategy3', false);

        $this->strategy->addStrategy($mockStrategy1);
        $this->strategy->addStrategy($mockStrategy2);
        $this->strategy->addStrategy($mockStrategy3);

        $config = [
            'operator' => 'OR',
            'strategies' => [
                ['type' => 'strategy1'],
                ['type' => 'strategy2'],
                ['type' => 'strategy3'],
            ],
        ];

        // Act: Evaluate
        $result = $this->strategy->isEnabled($config);

        // Assert: All failed, result is false
        $this->assertFalse($result);
    }

    public function testItShortCircuitsOnFirstSuccessWithOrOperator(): void
    {
        // Arrange: Two strategies, first passes
        $mockStrategy1 = $this->createMock(StrategyInterface::class);
        $mockStrategy1->method('getName')->willReturn('strategy1');
        $mockStrategy1->expects($this->once()) // Should be called
            ->method('isEnabled')
            ->willReturn(true);

        $mockStrategy2 = $this->createMock(StrategyInterface::class);
        $mockStrategy2->method('getName')->willReturn('strategy2');
        $mockStrategy2->expects($this->never()) // Should NOT be called (short-circuit)
            ->method('isEnabled');

        $this->strategy->addStrategy($mockStrategy1);
        $this->strategy->addStrategy($mockStrategy2);

        $config = [
            'operator' => 'OR',
            'strategies' => [
                ['type' => 'strategy1'],
                ['type' => 'strategy2'],
            ],
        ];

        // Act: Evaluate
        $result = $this->strategy->isEnabled($config);

        // Assert: Short-circuited, result is true
        $this->assertTrue($result);
    }

    public function testItPassesContextToChildStrategies(): void
    {
        // Arrange: Mock strategy that expects context
        $context = ['user_id' => 123, 'session_id' => 'abc'];
        $strategyConfig = ['type' => 'test_strategy', 'percentage' => 50];

        $mockStrategy = $this->createMock(StrategyInterface::class);
        $mockStrategy->method('getName')->willReturn('test_strategy');
        $mockStrategy->expects($this->once())
            ->method('isEnabled')
            ->with($strategyConfig, $context)
            ->willReturn(true);

        $this->strategy->addStrategy($mockStrategy);

        $config = [
            'operator' => 'AND',
            'strategies' => [$strategyConfig],
        ];

        // Act: Evaluate with context
        $result = $this->strategy->isEnabled($config, $context);

        // Assert: Context passed correctly
        $this->assertTrue($result);
    }

    public function testItSkipsUnknownStrategyTypes(): void
    {
        // Arrange: One known strategy, one unknown
        $mockStrategy = $this->createMockStrategy('known_strategy', true);
        $this->strategy->addStrategy($mockStrategy);

        $config = [
            'operator' => 'AND',
            'strategies' => [
                ['type' => 'unknown_strategy'], // Not registered
                ['type' => 'known_strategy'],   // Registered
            ],
        ];

        // Act: Evaluate (should skip unknown)
        $result = $this->strategy->isEnabled($config);

        // Assert: Known strategy evaluated, unknown skipped
        $this->assertTrue($result);
    }

    public function testItSkipsStrategiesWithoutType(): void
    {
        // Arrange: Strategy with missing type field
        $mockStrategy = $this->createMockStrategy('valid_strategy', true);
        $this->strategy->addStrategy($mockStrategy);

        $config = [
            'operator' => 'AND',
            'strategies' => [
                ['percentage' => 50], // Missing 'type' field
                ['type' => 'valid_strategy'],
            ],
        ];

        // Act: Evaluate
        $result = $this->strategy->isEnabled($config);

        // Assert: Invalid strategy skipped
        $this->assertTrue($result);
    }

    public function testItHandlesSingleStrategyWithAndOperator(): void
    {
        // Arrange: Single strategy
        $mockStrategy = $this->createMockStrategy('single_strategy', true);
        $this->strategy->addStrategy($mockStrategy);

        $config = [
            'operator' => 'AND',
            'strategies' => [
                ['type' => 'single_strategy'],
            ],
        ];

        // Act: Evaluate
        $result = $this->strategy->isEnabled($config);

        // Assert: Single strategy result returned
        $this->assertTrue($result);
    }

    public function testItHandlesSingleStrategyWithOrOperator(): void
    {
        // Arrange: Single strategy
        $mockStrategy = $this->createMockStrategy('single_strategy', false);
        $this->strategy->addStrategy($mockStrategy);

        $config = [
            'operator' => 'OR',
            'strategies' => [
                ['type' => 'single_strategy'],
            ],
        ];

        // Act: Evaluate
        $result = $this->strategy->isEnabled($config);

        // Assert: Single strategy result returned
        $this->assertFalse($result);
    }

    public function testItHandlesAllUnknownStrategiesWithAndOperator(): void
    {
        // Arrange: All strategies unknown
        $config = [
            'operator' => 'AND',
            'strategies' => [
                ['type' => 'unknown1'],
                ['type' => 'unknown2'],
            ],
        ];

        // Act: Evaluate
        $result = $this->strategy->isEnabled($config);

        // Assert: No strategies evaluated, AND returns true
        $this->assertTrue($result);
    }

    public function testItHandlesAllUnknownStrategiesWithOrOperator(): void
    {
        // Arrange: All strategies unknown
        $config = [
            'operator' => 'OR',
            'strategies' => [
                ['type' => 'unknown1'],
                ['type' => 'unknown2'],
            ],
        ];

        // Act: Evaluate
        $result = $this->strategy->isEnabled($config);

        // Assert: No strategies evaluated, OR returns false
        $this->assertFalse($result);
    }

    public function testItAllowsAddingMultipleStrategies(): void
    {
        // Arrange: Add multiple strategies
        $strategy1 = $this->createMockStrategy('strategy1', true);
        $strategy2 = $this->createMockStrategy('strategy2', true);
        $strategy3 = $this->createMockStrategy('strategy3', true);

        // Act: Add all strategies
        $this->strategy->addStrategy($strategy1);
        $this->strategy->addStrategy($strategy2);
        $this->strategy->addStrategy($strategy3);

        $config = [
            'strategies' => [
                ['type' => 'strategy1'],
                ['type' => 'strategy2'],
                ['type' => 'strategy3'],
            ],
        ];

        // Act: Evaluate
        $result = $this->strategy->isEnabled($config);

        // Assert: All strategies available
        $this->assertTrue($result);
    }

    public function testItOverwritesStrategyWhenAddingWithSameName(): void
    {
        // Arrange: Two strategies with same name
        $strategy1 = $this->createMockStrategy('duplicate', false);
        $strategy2 = $this->createMockStrategy('duplicate', true);

        // Act: Add both (second should overwrite first)
        $this->strategy->addStrategy($strategy1);
        $this->strategy->addStrategy($strategy2);

        $config = [
            'strategies' => [
                ['type' => 'duplicate'],
            ],
        ];

        $result = $this->strategy->isEnabled($config);

        // Assert: Second strategy used (returns true)
        $this->assertTrue($result);
    }

    /**
     * Helper method to create mock strategy with specific result
     */
    private function createMockStrategy(string $name, bool $result): StrategyInterface
    {
        $mock = $this->createMock(StrategyInterface::class);
        $mock->method('getName')->willReturn($name);
        $mock->method('isEnabled')->willReturn($result);

        return $mock;
    }
}
