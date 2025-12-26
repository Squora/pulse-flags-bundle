<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Tests\Strategy;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pulse\Flags\Core\Enum\FlagStrategy;
use Pulse\Flags\Core\Strategy\SimpleStrategy;
use Pulse\Flags\Core\Strategy\StrategyInterface;

final class SimpleStrategyTest extends TestCase
{
    #[Test]
    public function it_implements_strategy_interface(): void
    {
        // Arrange
        $strategy = new SimpleStrategy();

        // Act & Assert
        self::assertInstanceOf(StrategyInterface::class, $strategy);
    }

    #[Test]
    public function it_returns_correct_strategy_name(): void
    {
        // Arrange
        $strategy = new SimpleStrategy();

        // Act
        $name = $strategy->getName();

        // Assert
        self::assertSame('simple', $name);
        self::assertSame(FlagStrategy::SIMPLE->value, $name);
    }

    #[Test]
    public function it_is_always_enabled(): void
    {
        // Arrange
        $strategy = new SimpleStrategy();

        // Act
        $result = $strategy->isEnabled(config: [], context: []);

        // Assert
        self::assertTrue($result);
    }

    #[Test]
    public function it_is_enabled_with_empty_config(): void
    {
        // Arrange
        $strategy = new SimpleStrategy();

        // Act
        $result = $strategy->isEnabled(config: []);

        // Assert
        self::assertTrue($result);
    }

    #[Test]
    public function it_is_enabled_with_any_config(): void
    {
        // Arrange
        $strategy = new SimpleStrategy();

        // Act & Assert
        self::assertTrue($strategy->isEnabled(config: ['key' => 'value']));
        self::assertTrue($strategy->isEnabled(config: ['percentage' => 50]));
        self::assertTrue($strategy->isEnabled(config: ['enabled' => false]));
        self::assertTrue($strategy->isEnabled(config: ['complex' => ['nested' => 'data']]));
    }

    #[Test]
    public function it_is_enabled_with_empty_context(): void
    {
        // Arrange
        $strategy = new SimpleStrategy();

        // Act
        $result = $strategy->isEnabled(config: [], context: []);

        // Assert
        self::assertTrue($result);
    }

    #[Test]
    public function it_is_enabled_with_any_context(): void
    {
        // Arrange
        $strategy = new SimpleStrategy();

        // Act & Assert
        self::assertTrue($strategy->isEnabled(config: [], context: ['user_id' => '123']));
        self::assertTrue($strategy->isEnabled(config: [], context: ['session_id' => 'abc']));
        self::assertTrue($strategy->isEnabled(config: [], context: ['ip' => '192.168.1.1']));
        self::assertTrue($strategy->isEnabled(config: [], context: ['country' => 'US']));
        self::assertTrue($strategy->isEnabled(config: [], context: ['custom' => ['data' => 'value']]));
    }

    #[Test]
    public function it_is_enabled_with_both_config_and_context(): void
    {
        // Arrange
        $strategy = new SimpleStrategy();

        // Act
        $result = $strategy->isEnabled(
            config: ['some' => 'config'],
            context: ['some' => 'context']
        );

        // Assert
        self::assertTrue($result);
    }

    #[Test]
    public function it_is_consistently_enabled_across_multiple_calls(): void
    {
        // Arrange
        $strategy = new SimpleStrategy();

        // Act
        $result1 = $strategy->isEnabled(config: []);
        $result2 = $strategy->isEnabled(config: []);
        $result3 = $strategy->isEnabled(config: []);

        // Assert
        self::assertTrue($result1);
        self::assertTrue($result2);
        self::assertTrue($result3);
    }

    #[Test]
    public function it_ignores_config_parameters(): void
    {
        // Arrange
        $strategy = new SimpleStrategy();

        // Act & Assert - config doesn't affect result
        self::assertTrue($strategy->isEnabled(config: ['enabled' => true]));
        self::assertTrue($strategy->isEnabled(config: ['enabled' => false]));
        self::assertTrue($strategy->isEnabled(config: ['percentage' => 0]));
        self::assertTrue($strategy->isEnabled(config: ['percentage' => 100]));
    }

    #[Test]
    public function it_ignores_context_parameters(): void
    {
        // Arrange
        $strategy = new SimpleStrategy();

        // Act & Assert - context doesn't affect result
        self::assertTrue($strategy->isEnabled(config: [], context: ['user_id' => null]));
        self::assertTrue($strategy->isEnabled(config: [], context: ['user_id' => '']));
        self::assertTrue($strategy->isEnabled(config: [], context: ['user_id' => 'admin']));
        self::assertTrue($strategy->isEnabled(config: [], context: ['user_id' => 'regular']));
    }

    #[Test]
    public function it_is_stateless(): void
    {
        // Arrange
        $strategy = new SimpleStrategy();

        // Act - call with different parameters
        $result1 = $strategy->isEnabled(config: ['a' => 1], context: ['x' => 1]);
        $result2 = $strategy->isEnabled(config: ['b' => 2], context: ['y' => 2]);
        $result3 = $strategy->isEnabled(config: ['c' => 3], context: ['z' => 3]);

        // Assert - all return true, no state is maintained
        self::assertTrue($result1);
        self::assertTrue($result2);
        self::assertTrue($result3);
    }

    #[Test]
    public function it_works_with_real_world_config(): void
    {
        // Arrange
        $strategy = new SimpleStrategy();
        $config = [
            'flag_key' => 'new-feature',
            'description' => 'New feature rollout',
            'enabled' => true,
            'created_at' => '2025-01-01',
        ];

        // Act
        $result = $strategy->isEnabled(config: $config);

        // Assert
        self::assertTrue($result);
    }

    #[Test]
    public function it_works_with_real_world_context(): void
    {
        // Arrange
        $strategy = new SimpleStrategy();
        $context = [
            'user_id' => 'user-123',
            'session_id' => 'sess-abc-def',
            'ip' => '192.168.1.1',
            'country' => 'US',
            'timestamp' => 1735819200,
        ];

        // Act
        $result = $strategy->isEnabled(config: [], context: $context);

        // Assert
        self::assertTrue($result);
    }
}
