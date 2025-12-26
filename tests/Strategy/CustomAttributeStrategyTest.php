<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Tests\Strategy;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pulse\Flags\Core\Enum\FlagStrategy;
use Pulse\Flags\Core\Strategy\CustomAttributeStrategy;
use Pulse\Flags\Core\Strategy\Operator\ContainsOperator;
use Pulse\Flags\Core\Strategy\Operator\EndsWithOperator;
use Pulse\Flags\Core\Strategy\Operator\EqualsOperator;
use Pulse\Flags\Core\Strategy\Operator\GreaterThanOperator;
use Pulse\Flags\Core\Strategy\Operator\GreaterThanOrEqualsOperator;
use Pulse\Flags\Core\Strategy\Operator\InOperator;
use Pulse\Flags\Core\Strategy\Operator\LessThanOperator;
use Pulse\Flags\Core\Strategy\Operator\LessThanOrEqualsOperator;
use Pulse\Flags\Core\Strategy\Operator\NotContainsOperator;
use Pulse\Flags\Core\Strategy\Operator\NotEqualsOperator;
use Pulse\Flags\Core\Strategy\Operator\NotInOperator;
use Pulse\Flags\Core\Strategy\Operator\RegexOperator;
use Pulse\Flags\Core\Strategy\Operator\StartsWithOperator;
use Pulse\Flags\Core\Strategy\StrategyInterface;

final class CustomAttributeStrategyTest extends TestCase
{
    private function createStrategy(): CustomAttributeStrategy
    {
        $operators = [
            new EqualsOperator(),
            new NotEqualsOperator(),
            new GreaterThanOperator(),
            new GreaterThanOrEqualsOperator(),
            new LessThanOperator(),
            new LessThanOrEqualsOperator(),
            new InOperator(),
            new NotInOperator(),
            new ContainsOperator(),
            new NotContainsOperator(),
            new StartsWithOperator(),
            new EndsWithOperator(),
            new RegexOperator(),
        ];

        return new CustomAttributeStrategy($operators);
    }

    #[Test]
    public function it_implements_strategy_interface(): void
    {
        // Arrange
        $strategy = $this->createStrategy();

        // Act & Assert
        self::assertInstanceOf(StrategyInterface::class, $strategy);
    }

    #[Test]
    public function it_returns_correct_strategy_name(): void
    {
        // Arrange
        $strategy = $this->createStrategy();

        // Act
        $name = $strategy->getName();

        // Assert
        self::assertSame('custom_attribute', $name);
        self::assertSame(FlagStrategy::CUSTOM_ATTRIBUTE->value, $name);
    }

    #[Test]
    public function it_is_enabled_when_single_rule_matches(): void
    {
        // Arrange
        $strategy = $this->createStrategy();
        $config = [
            'rules' => [
                [
                    'attribute' => 'subscription_tier',
                    'operator' => 'equals',
                    'value' => 'premium',
                ],
            ],
        ];
        $context = ['subscription_tier' => 'premium'];

        // Act
        $result = $strategy->isEnabled($config, $context);

        // Assert
        self::assertTrue($result);
    }

    #[Test]
    public function it_is_disabled_when_single_rule_does_not_match(): void
    {
        // Arrange
        $strategy = $this->createStrategy();
        $config = [
            'rules' => [
                [
                    'attribute' => 'subscription_tier',
                    'operator' => 'equals',
                    'value' => 'premium',
                ],
            ],
        ];
        $context = ['subscription_tier' => 'free'];

        // Act
        $result = $strategy->isEnabled($config, $context);

        // Assert
        self::assertFalse($result);
    }

    #[Test]
    public function it_uses_and_logic_for_multiple_rules(): void
    {
        // Arrange - all rules must pass
        $strategy = $this->createStrategy();
        $config = [
            'rules' => [
                ['attribute' => 'subscription_tier', 'operator' => 'equals', 'value' => 'premium'],
                ['attribute' => 'account_age_days', 'operator' => 'greater_than', 'value' => 30],
            ],
        ];

        // Act & Assert - both rules must match
        self::assertTrue($strategy->isEnabled($config, [
            'subscription_tier' => 'premium',
            'account_age_days' => 60,
        ]));

        self::assertFalse($strategy->isEnabled($config, [
            'subscription_tier' => 'free', // First rule fails
            'account_age_days' => 60,
        ]));

        self::assertFalse($strategy->isEnabled($config, [
            'subscription_tier' => 'premium',
            'account_age_days' => 10, // Second rule fails
        ]));
    }

    #[Test]
    public function it_returns_false_when_no_rules_configured(): void
    {
        // Arrange
        $strategy = $this->createStrategy();
        $config = ['rules' => []];

        // Act
        $result = $strategy->isEnabled($config, ['any' => 'value']);

        // Assert
        self::assertFalse($result);
    }

    #[Test]
    public function it_returns_false_when_rules_key_missing(): void
    {
        // Arrange
        $strategy = $this->createStrategy();
        $config = [];

        // Act
        $result = $strategy->isEnabled($config, ['any' => 'value']);

        // Assert
        self::assertFalse($result);
    }

    #[Test]
    public function it_returns_false_when_attribute_missing_in_context(): void
    {
        // Arrange
        $strategy = $this->createStrategy();
        $config = [
            'rules' => [
                ['attribute' => 'subscription_tier', 'operator' => 'equals', 'value' => 'premium'],
            ],
        ];

        // Act
        $result = $strategy->isEnabled($config, context: []); // Missing attribute

        // Assert
        self::assertFalse($result);
    }

    #[Test]
    public function it_returns_false_when_attribute_key_missing_in_rule(): void
    {
        // Arrange
        $strategy = $this->createStrategy();
        $config = [
            'rules' => [
                ['operator' => 'equals', 'value' => 'premium'], // Missing 'attribute'
            ],
        ];

        // Act
        $result = $strategy->isEnabled($config, ['subscription_tier' => 'premium']);

        // Assert
        self::assertFalse($result);
    }

    #[Test]
    public function it_returns_false_when_operator_key_missing_in_rule(): void
    {
        // Arrange
        $strategy = $this->createStrategy();
        $config = [
            'rules' => [
                ['attribute' => 'subscription_tier', 'value' => 'premium'], // Missing 'operator'
            ],
        ];

        // Act
        $result = $strategy->isEnabled($config, ['subscription_tier' => 'premium']);

        // Assert
        self::assertFalse($result);
    }

    #[Test]
    public function it_returns_false_for_unknown_operator(): void
    {
        // Arrange
        $strategy = $this->createStrategy();
        $config = [
            'rules' => [
                ['attribute' => 'tier', 'operator' => 'unknown_operator', 'value' => 'premium'],
            ],
        ];

        // Act
        $result = $strategy->isEnabled($config, ['tier' => 'premium']);

        // Assert
        self::assertFalse($result);
    }

    #[Test]
    public function it_supports_equals_operator(): void
    {
        // Arrange
        $strategy = $this->createStrategy();
        $config = [
            'rules' => [
                ['attribute' => 'tier', 'operator' => 'equals', 'value' => 'premium'],
            ],
        ];

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, ['tier' => 'premium']));
        self::assertFalse($strategy->isEnabled($config, ['tier' => 'free']));
    }

    #[Test]
    public function it_supports_not_equals_operator(): void
    {
        // Arrange
        $strategy = $this->createStrategy();
        $config = [
            'rules' => [
                ['attribute' => 'tier', 'operator' => 'not_equals', 'value' => 'banned'],
            ],
        ];

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, ['tier' => 'premium']));
        self::assertTrue($strategy->isEnabled($config, ['tier' => 'free']));
        self::assertFalse($strategy->isEnabled($config, ['tier' => 'banned']));
    }

    #[Test]
    public function it_supports_in_operator(): void
    {
        // Arrange
        $strategy = $this->createStrategy();
        $config = [
            'rules' => [
                ['attribute' => 'tier', 'operator' => 'in', 'values' => ['premium', 'enterprise']],
            ],
        ];

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, ['tier' => 'premium']));
        self::assertTrue($strategy->isEnabled($config, ['tier' => 'enterprise']));
        self::assertFalse($strategy->isEnabled($config, ['tier' => 'free']));
    }

    #[Test]
    public function it_supports_not_in_operator(): void
    {
        // Arrange
        $strategy = $this->createStrategy();
        $config = [
            'rules' => [
                ['attribute' => 'tier', 'operator' => 'not_in', 'values' => ['banned', 'suspended']],
            ],
        ];

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, ['tier' => 'premium']));
        self::assertTrue($strategy->isEnabled($config, ['tier' => 'free']));
        self::assertFalse($strategy->isEnabled($config, ['tier' => 'banned']));
    }

    #[Test]
    public function it_supports_greater_than_operator(): void
    {
        // Arrange
        $strategy = $this->createStrategy();
        $config = [
            'rules' => [
                ['attribute' => 'age', 'operator' => 'greater_than', 'value' => 18],
            ],
        ];

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, ['age' => 25]));
        self::assertFalse($strategy->isEnabled($config, ['age' => 18]));
        self::assertFalse($strategy->isEnabled($config, ['age' => 15]));
    }

    #[Test]
    public function it_supports_greater_than_or_equals_operator(): void
    {
        // Arrange
        $strategy = $this->createStrategy();
        $config = [
            'rules' => [
                ['attribute' => 'age', 'operator' => 'greater_than_or_equals', 'value' => 18],
            ],
        ];

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, ['age' => 25]));
        self::assertTrue($strategy->isEnabled($config, ['age' => 18]));
        self::assertFalse($strategy->isEnabled($config, ['age' => 15]));
    }

    #[Test]
    public function it_supports_less_than_operator(): void
    {
        // Arrange
        $strategy = $this->createStrategy();
        $config = [
            'rules' => [
                ['attribute' => 'price', 'operator' => 'less_than', 'value' => 100],
            ],
        ];

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, ['price' => 50]));
        self::assertFalse($strategy->isEnabled($config, ['price' => 100]));
        self::assertFalse($strategy->isEnabled($config, ['price' => 150]));
    }

    #[Test]
    public function it_supports_less_than_or_equals_operator(): void
    {
        // Arrange
        $strategy = $this->createStrategy();
        $config = [
            'rules' => [
                ['attribute' => 'price', 'operator' => 'less_than_or_equals', 'value' => 100],
            ],
        ];

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, ['price' => 50]));
        self::assertTrue($strategy->isEnabled($config, ['price' => 100]));
        self::assertFalse($strategy->isEnabled($config, ['price' => 150]));
    }

    #[Test]
    public function it_supports_contains_operator(): void
    {
        // Arrange
        $strategy = $this->createStrategy();
        $config = [
            'rules' => [
                ['attribute' => 'email', 'operator' => 'contains', 'value' => '@company.com'],
            ],
        ];

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, ['email' => 'user@company.com']));
        self::assertFalse($strategy->isEnabled($config, ['email' => 'user@gmail.com']));
    }

    #[Test]
    public function it_supports_not_contains_operator(): void
    {
        // Arrange
        $strategy = $this->createStrategy();
        $config = [
            'rules' => [
                ['attribute' => 'email', 'operator' => 'not_contains', 'value' => '@competitor.com'],
            ],
        ];

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, ['email' => 'user@company.com']));
        self::assertFalse($strategy->isEnabled($config, ['email' => 'user@competitor.com']));
    }

    #[Test]
    public function it_supports_starts_with_operator(): void
    {
        // Arrange
        $strategy = $this->createStrategy();
        $config = [
            'rules' => [
                ['attribute' => 'user_id', 'operator' => 'starts_with', 'value' => 'admin-'],
            ],
        ];

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, ['user_id' => 'admin-123']));
        self::assertFalse($strategy->isEnabled($config, ['user_id' => 'user-123']));
    }

    #[Test]
    public function it_supports_ends_with_operator(): void
    {
        // Arrange
        $strategy = $this->createStrategy();
        $config = [
            'rules' => [
                ['attribute' => 'email', 'operator' => 'ends_with', 'value' => '@company.com'],
            ],
        ];

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, ['email' => 'user@company.com']));
        self::assertFalse($strategy->isEnabled($config, ['email' => 'user@gmail.com']));
    }

    #[Test]
    public function it_supports_regex_operator(): void
    {
        // Arrange
        $strategy = $this->createStrategy();
        $config = [
            'rules' => [
                ['attribute' => 'email', 'operator' => 'regex', 'value' => '/^[a-z]+@company\.com$/'],
            ],
        ];

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, ['email' => 'user@company.com']));
        self::assertFalse($strategy->isEnabled($config, ['email' => 'User123@company.com']));
    }

    #[Test]
    public function it_works_with_premium_user_scenario(): void
    {
        // Arrange - Enable for premium users with old accounts
        $strategy = $this->createStrategy();
        $config = [
            'rules' => [
                ['attribute' => 'subscription_tier', 'operator' => 'in', 'values' => ['premium', 'enterprise']],
                ['attribute' => 'account_age_days', 'operator' => 'greater_than', 'value' => 30],
            ],
        ];

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, [
            'subscription_tier' => 'premium',
            'account_age_days' => 60,
        ]));

        self::assertFalse($strategy->isEnabled($config, [
            'subscription_tier' => 'free', // Not premium
            'account_age_days' => 60,
        ]));

        self::assertFalse($strategy->isEnabled($config, [
            'subscription_tier' => 'premium',
            'account_age_days' => 10, // Account too new
        ]));
    }

    #[Test]
    public function it_works_with_internal_users_scenario(): void
    {
        // Arrange - Enable for company employees
        $strategy = $this->createStrategy();
        $config = [
            'rules' => [
                ['attribute' => 'email', 'operator' => 'ends_with', 'value' => '@company.com'],
            ],
        ];

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, ['email' => 'employee@company.com']));
        self::assertFalse($strategy->isEnabled($config, ['email' => 'customer@gmail.com']));
    }

    #[Test]
    public function it_works_with_regional_rollout_scenario(): void
    {
        // Arrange - Enable for specific countries with premium tier
        $strategy = $this->createStrategy();
        $config = [
            'rules' => [
                ['attribute' => 'country', 'operator' => 'in', 'values' => ['US', 'CA', 'GB']],
                ['attribute' => 'subscription_tier', 'operator' => 'equals', 'value' => 'premium'],
            ],
        ];

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, [
            'country' => 'US',
            'subscription_tier' => 'premium',
        ]));

        self::assertFalse($strategy->isEnabled($config, [
            'country' => 'FR', // Wrong country
            'subscription_tier' => 'premium',
        ]));
    }

    #[Test]
    public function it_handles_context_with_additional_fields(): void
    {
        // Arrange
        $strategy = $this->createStrategy();
        $config = [
            'rules' => [
                ['attribute' => 'tier', 'operator' => 'equals', 'value' => 'premium'],
            ],
        ];
        $context = [
            'tier' => 'premium',
            'user_id' => 'user-123',
            'session_id' => 'sess-abc',
            'other_field' => 'ignored',
        ];

        // Act
        $result = $strategy->isEnabled($config, $context);

        // Assert - ignores extra fields
        self::assertTrue($result);
    }
}
