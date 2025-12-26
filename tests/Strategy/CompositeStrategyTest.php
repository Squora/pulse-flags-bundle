<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Tests\Strategy;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pulse\Flags\Core\Enum\FlagStrategy;
use Pulse\Flags\Core\Strategy\CompositeStrategy;
use Pulse\Flags\Core\Strategy\SimpleStrategy;
use Pulse\Flags\Core\Strategy\StrategyInterface;

final class CompositeStrategyTest extends TestCase
{
    private function createMockStrategy(string $name, bool $result): StrategyInterface
    {
        return new class ($name, $result) implements StrategyInterface {
            public function __construct(private string $name, private bool $result)
            {
            }

            public function isEnabled(array $config, array $context = []): bool
            {
                return $this->result;
            }

            public function getName(): string
            {
                return $this->name;
            }
        };
    }

    #[Test]
    public function it_implements_strategy_interface(): void
    {
        // Arrange
        $strategy = new CompositeStrategy();

        // Act & Assert
        self::assertInstanceOf(StrategyInterface::class, $strategy);
    }

    #[Test]
    public function it_returns_correct_strategy_name(): void
    {
        // Arrange
        $strategy = new CompositeStrategy();

        // Act
        $name = $strategy->getName();

        // Assert
        self::assertSame('composite', $name);
        self::assertSame(FlagStrategy::COMPOSITE->value, $name);
    }

    #[Test]
    public function it_returns_true_when_no_strategies_configured(): void
    {
        // Arrange
        $strategy = new CompositeStrategy();
        $config = ['strategies' => []];

        // Act
        $result = $strategy->isEnabled($config, context: []);

        // Assert
        self::assertTrue($result);
    }

    #[Test]
    public function it_returns_true_when_strategies_key_missing(): void
    {
        // Arrange
        $strategy = new CompositeStrategy();
        $config = [];

        // Act
        $result = $strategy->isEnabled($config, context: []);

        // Assert
        self::assertTrue($result);
    }

    #[Test]
    public function it_uses_and_operator_by_default(): void
    {
        // Arrange
        $composite = new CompositeStrategy();
        $composite->addStrategy($this->createMockStrategy('mock1', true));
        $composite->addStrategy($this->createMockStrategy('mock2', true));

        $config = [
            'strategies' => [
                ['type' => 'mock1'],
                ['type' => 'mock2'],
            ],
            // No operator specified - should default to AND
        ];

        // Act
        $result = $composite->isEnabled($config, []);

        // Assert - both must be true for AND
        self::assertTrue($result);
    }

    #[Test]
    public function it_uses_and_logic_when_operator_is_and(): void
    {
        // Arrange
        $composite = new CompositeStrategy();
        $composite->addStrategy($this->createMockStrategy('mock1', true));
        $composite->addStrategy($this->createMockStrategy('mock2', true));

        $config = [
            'operator' => 'AND',
            'strategies' => [
                ['type' => 'mock1'],
                ['type' => 'mock2'],
            ],
        ];

        // Act
        $result = $composite->isEnabled($config, []);

        // Assert - both true, result is true
        self::assertTrue($result);
    }

    #[Test]
    public function it_returns_false_with_and_when_any_strategy_fails(): void
    {
        // Arrange
        $composite = new CompositeStrategy();
        $composite->addStrategy($this->createMockStrategy('mock1', true));
        $composite->addStrategy($this->createMockStrategy('mock2', false)); // This one fails
        $composite->addStrategy($this->createMockStrategy('mock3', true));

        $config = [
            'operator' => 'AND',
            'strategies' => [
                ['type' => 'mock1'],
                ['type' => 'mock2'],
                ['type' => 'mock3'],
            ],
        ];

        // Act
        $result = $composite->isEnabled($config, []);

        // Assert - one false makes AND false
        self::assertFalse($result);
    }

    #[Test]
    public function it_short_circuits_and_on_first_false(): void
    {
        // Arrange
        $composite = new CompositeStrategy();
        $composite->addStrategy($this->createMockStrategy('mock1', false)); // Fails first
        $composite->addStrategy($this->createMockStrategy('mock2', true));  // Should not be evaluated

        $config = [
            'operator' => 'AND',
            'strategies' => [
                ['type' => 'mock1'],
                ['type' => 'mock2'],
            ],
        ];

        // Act
        $result = $composite->isEnabled($config, []);

        // Assert
        self::assertFalse($result);
    }

    #[Test]
    public function it_uses_or_logic_when_operator_is_or(): void
    {
        // Arrange
        $composite = new CompositeStrategy();
        $composite->addStrategy($this->createMockStrategy('mock1', true));
        $composite->addStrategy($this->createMockStrategy('mock2', false));

        $config = [
            'operator' => 'OR',
            'strategies' => [
                ['type' => 'mock1'],
                ['type' => 'mock2'],
            ],
        ];

        // Act
        $result = $composite->isEnabled($config, []);

        // Assert - one true is enough for OR
        self::assertTrue($result);
    }

    #[Test]
    public function it_returns_false_with_or_when_all_strategies_fail(): void
    {
        // Arrange
        $composite = new CompositeStrategy();
        $composite->addStrategy($this->createMockStrategy('mock1', false));
        $composite->addStrategy($this->createMockStrategy('mock2', false));
        $composite->addStrategy($this->createMockStrategy('mock3', false));

        $config = [
            'operator' => 'OR',
            'strategies' => [
                ['type' => 'mock1'],
                ['type' => 'mock2'],
                ['type' => 'mock3'],
            ],
        ];

        // Act
        $result = $composite->isEnabled($config, []);

        // Assert - all false makes OR false
        self::assertFalse($result);
    }

    #[Test]
    public function it_short_circuits_or_on_first_true(): void
    {
        // Arrange
        $composite = new CompositeStrategy();
        $composite->addStrategy($this->createMockStrategy('mock1', true)); // Succeeds first
        $composite->addStrategy($this->createMockStrategy('mock2', false)); // Should not matter

        $config = [
            'operator' => 'OR',
            'strategies' => [
                ['type' => 'mock1'],
                ['type' => 'mock2'],
            ],
        ];

        // Act
        $result = $composite->isEnabled($config, []);

        // Assert
        self::assertTrue($result);
    }

    #[Test]
    public function it_skips_strategies_without_type_field(): void
    {
        // Arrange
        $composite = new CompositeStrategy();
        $composite->addStrategy($this->createMockStrategy('mock1', true));

        $config = [
            'operator' => 'AND',
            'strategies' => [
                ['no_type_field' => 'value'], // Missing type field
                ['type' => 'mock1'],
            ],
        ];

        // Act
        $result = $composite->isEnabled($config, []);

        // Assert - skips invalid strategy, evaluates valid one
        self::assertTrue($result);
    }

    #[Test]
    public function it_skips_unknown_strategy_types(): void
    {
        // Arrange
        $composite = new CompositeStrategy();
        $composite->addStrategy($this->createMockStrategy('mock1', true));

        $config = [
            'operator' => 'AND',
            'strategies' => [
                ['type' => 'unknown_strategy'], // Not registered
                ['type' => 'mock1'],
            ],
        ];

        // Act
        $result = $composite->isEnabled($config, []);

        // Assert - skips unknown strategy, evaluates registered one
        self::assertTrue($result);
    }

    #[Test]
    public function it_passes_context_to_child_strategies(): void
    {
        // Arrange
        $contextReceived = null;
        $mockStrategy = new class ($contextReceived) implements StrategyInterface {
            public function __construct(private &$contextRef)
            {
            }

            public function isEnabled(array $config, array $context = []): bool
            {
                $this->contextRef = $context;
                return true;
            }

            public function getName(): string
            {
                return 'context_checker';
            }
        };

        $composite = new CompositeStrategy();
        $composite->addStrategy($mockStrategy);

        $config = ['strategies' => [['type' => 'context_checker']]];
        $context = ['user_id' => 'user-123', 'country' => 'US'];

        // Act
        $composite->isEnabled($config, $context);

        // Assert - context was passed
        self::assertSame($context, $contextReceived);
    }

    #[Test]
    public function it_passes_config_to_child_strategies(): void
    {
        // Arrange
        $configReceived = null;
        $mockStrategy = new class ($configReceived) implements StrategyInterface {
            public function __construct(private &$configRef)
            {
            }

            public function isEnabled(array $config, array $context = []): bool
            {
                $this->configRef = $config;
                return true;
            }

            public function getName(): string
            {
                return 'config_checker';
            }
        };

        $composite = new CompositeStrategy();
        $composite->addStrategy($mockStrategy);

        $childConfig = ['type' => 'config_checker', 'some_param' => 'value'];
        $config = ['strategies' => [$childConfig]];

        // Act
        $composite->isEnabled($config, []);

        // Assert - child config was passed
        self::assertSame($childConfig, $configReceived);
    }

    #[Test]
    public function it_works_with_real_strategies(): void
    {
        // Arrange
        $composite = new CompositeStrategy();
        $composite->addStrategy(new SimpleStrategy());

        $config = [
            'operator' => 'AND',
            'strategies' => [
                ['type' => 'simple'],
            ],
        ];

        // Act
        $result = $composite->isEnabled($config, []);

        // Assert - SimpleStrategy always returns true
        self::assertTrue($result);
    }

    #[Test]
    public function it_works_with_and_scenario_all_true(): void
    {
        // Arrange - All conditions must be met
        $composite = new CompositeStrategy();
        $composite->addStrategy($this->createMockStrategy('percentage', true));
        $composite->addStrategy($this->createMockStrategy('date_range', true));
        $composite->addStrategy($this->createMockStrategy('geo', true));

        $config = [
            'operator' => 'AND',
            'strategies' => [
                ['type' => 'percentage', 'percentage' => 50],
                ['type' => 'date_range', 'start_date' => '2025-01-01'],
                ['type' => 'geo', 'countries' => ['US']],
            ],
        ];

        // Act
        $result = $composite->isEnabled($config, []);

        // Assert
        self::assertTrue($result);
    }

    #[Test]
    public function it_works_with_and_scenario_one_false(): void
    {
        // Arrange - One condition fails
        $composite = new CompositeStrategy();
        $composite->addStrategy($this->createMockStrategy('percentage', true));
        $composite->addStrategy($this->createMockStrategy('date_range', false)); // Fails
        $composite->addStrategy($this->createMockStrategy('geo', true));

        $config = [
            'operator' => 'AND',
            'strategies' => [
                ['type' => 'percentage', 'percentage' => 50],
                ['type' => 'date_range', 'start_date' => '2025-01-01'],
                ['type' => 'geo', 'countries' => ['US']],
            ],
        ];

        // Act
        $result = $composite->isEnabled($config, []);

        // Assert
        self::assertFalse($result);
    }

    #[Test]
    public function it_works_with_or_scenario_all_false(): void
    {
        // Arrange - At least one condition must be met, but none are
        $composite = new CompositeStrategy();
        $composite->addStrategy($this->createMockStrategy('user_id', false));
        $composite->addStrategy($this->createMockStrategy('percentage', false));
        $composite->addStrategy($this->createMockStrategy('segment', false));

        $config = [
            'operator' => 'OR',
            'strategies' => [
                ['type' => 'user_id', 'whitelist' => [1, 2, 3]],
                ['type' => 'percentage', 'percentage' => 10],
                ['type' => 'segment', 'segments' => ['beta']],
            ],
        ];

        // Act
        $result = $composite->isEnabled($config, []);

        // Assert
        self::assertFalse($result);
    }

    #[Test]
    public function it_works_with_or_scenario_one_true(): void
    {
        // Arrange - At least one condition must be met
        $composite = new CompositeStrategy();
        $composite->addStrategy($this->createMockStrategy('user_id', false));
        $composite->addStrategy($this->createMockStrategy('percentage', true)); // This one succeeds
        $composite->addStrategy($this->createMockStrategy('segment', false));

        $config = [
            'operator' => 'OR',
            'strategies' => [
                ['type' => 'user_id', 'whitelist' => [1, 2, 3]],
                ['type' => 'percentage', 'percentage' => 10],
                ['type' => 'segment', 'segments' => ['beta']],
            ],
        ];

        // Act
        $result = $composite->isEnabled($config, []);

        // Assert
        self::assertTrue($result);
    }

    #[Test]
    public function it_handles_single_strategy(): void
    {
        // Arrange
        $composite = new CompositeStrategy();
        $composite->addStrategy($this->createMockStrategy('mock', true));

        $config = [
            'operator' => 'AND',
            'strategies' => [
                ['type' => 'mock'],
            ],
        ];

        // Act
        $result = $composite->isEnabled($config, []);

        // Assert
        self::assertTrue($result);
    }

    #[Test]
    public function it_handles_many_strategies_with_and(): void
    {
        // Arrange
        $composite = new CompositeStrategy();
        for ($i = 1; $i <= 10; $i++) {
            $composite->addStrategy($this->createMockStrategy("mock{$i}", true));
        }

        $strategies = [];
        for ($i = 1; $i <= 10; $i++) {
            $strategies[] = ['type' => "mock{$i}"];
        }

        $config = [
            'operator' => 'AND',
            'strategies' => $strategies,
        ];

        // Act
        $result = $composite->isEnabled($config, []);

        // Assert - all true
        self::assertTrue($result);
    }
}
