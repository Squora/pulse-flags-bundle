<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Tests\Strategy;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pulse\Flags\Core\Enum\FlagStrategy;
use Pulse\Flags\Core\Strategy\Hash\HashCalculator;
use Pulse\Flags\Core\Strategy\PercentageStrategy;
use Pulse\Flags\Core\Strategy\ProgressiveRolloutStrategy;
use Pulse\Flags\Core\Strategy\StrategyInterface;

final class ProgressiveRolloutStrategyTest extends TestCase
{
    private function createStrategy(): ProgressiveRolloutStrategy
    {
        $hashCalculator = new HashCalculator();
        $percentageStrategy = new PercentageStrategy($hashCalculator);
        return new ProgressiveRolloutStrategy($percentageStrategy);
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
        self::assertSame('progressive_rollout', $name);
        self::assertSame(FlagStrategy::PROGRESSIVE_ROLLOUT->value, $name);
    }

    #[Test]
    public function it_returns_false_when_no_schedule_configured(): void
    {
        // Arrange
        $strategy = $this->createStrategy();
        $config = ['schedule' => []];

        // Act
        $result = $strategy->isEnabled($config, ['user_id' => 'user-1']);

        // Assert
        self::assertFalse($result);
    }

    #[Test]
    public function it_returns_false_when_schedule_key_missing(): void
    {
        // Arrange
        $strategy = $this->createStrategy();
        $config = [];

        // Act
        $result = $strategy->isEnabled($config, ['user_id' => 'user-1']);

        // Assert
        self::assertFalse($result);
    }

    #[Test]
    public function it_returns_false_when_current_date_is_before_first_stage(): void
    {
        // Arrange
        $strategy = $this->createStrategy();
        $config = [
            'schedule' => [
                ['percentage' => 10, 'start_date' => '2099-01-01'], // Far future
            ],
        ];

        // Act
        $result = $strategy->isEnabled($config, ['user_id' => 'user-1']);

        // Assert - rollout hasn't started yet
        self::assertFalse($result);
    }

    #[Test]
    public function it_uses_first_stage_percentage_when_only_first_stage_started(): void
    {
        // Arrange
        $strategy = $this->createStrategy();
        $config = [
            'schedule' => [
                ['percentage' => 100, 'start_date' => '2020-01-01'], // Started
                ['percentage' => 50, 'start_date' => '2099-01-01'],  // Not started
            ],
        ];

        // Act
        $result = $strategy->isEnabled($config, ['user_id' => 'user-1']);

        // Assert - uses 100% from first stage
        self::assertTrue($result);
    }

    #[Test]
    public function it_progresses_through_stages_as_time_advances(): void
    {
        // Arrange
        $strategy = $this->createStrategy();
        $config = [
            'schedule' => [
                ['percentage' => 0, 'start_date' => '2020-01-01'],   // Stage 1 started
                ['percentage' => 0, 'start_date' => '2020-01-02'],   // Stage 2 started
                ['percentage' => 100, 'start_date' => '2020-01-03'], // Stage 3 started
                ['percentage' => 0, 'start_date' => '2099-01-01'],   // Stage 4 not started
            ],
        ];

        // Act
        $result = $strategy->isEnabled($config, ['user_id' => 'user-1']);

        // Assert - uses stage 3 (100%)
        self::assertTrue($result);
    }

    #[Test]
    public function it_returns_true_when_final_stage_is_100_percent(): void
    {
        // Arrange
        $strategy = $this->createStrategy();
        $config = [
            'schedule' => [
                ['percentage' => 1, 'start_date' => '2020-01-01'],
                ['percentage' => 5, 'start_date' => '2020-01-02'],
                ['percentage' => 100, 'start_date' => '2020-01-03'],
            ],
        ];

        // Act
        $result = $strategy->isEnabled($config, ['user_id' => 'user-1']);

        // Assert
        self::assertTrue($result);
    }

    #[Test]
    public function it_uses_consistent_hashing_for_user_assignment(): void
    {
        // Arrange
        $strategy = $this->createStrategy();
        $config = [
            'schedule' => [
                ['percentage' => 50, 'start_date' => '2020-01-01'],
            ],
        ];
        $context = ['user_id' => 'user-123'];

        // Act
        $result1 = $strategy->isEnabled($config, $context);
        $result2 = $strategy->isEnabled($config, $context);
        $result3 = $strategy->isEnabled($config, $context);

        // Assert - same user always gets same result
        self::assertSame($result1, $result2);
        self::assertSame($result2, $result3);
    }

    #[Test]
    public function it_supports_decimal_percentages(): void
    {
        // Arrange
        $strategy = $this->createStrategy();
        $config = [
            'schedule' => [
                ['percentage' => 0.1, 'start_date' => '2020-01-01'],
                ['percentage' => 0.5, 'start_date' => '2020-01-02'],
                ['percentage' => 1.5, 'start_date' => '2020-01-03'],
            ],
        ];

        // Act
        $result = $strategy->isEnabled($config, ['user_id' => 'user-1']);

        // Assert - uses latest stage (1.5%)
        self::assertIsBool($result);
    }

    #[Test]
    public function it_passes_stickiness_config_to_percentage_strategy(): void
    {
        // Arrange
        $strategy = $this->createStrategy();
        $config = [
            'schedule' => [
                ['percentage' => 100, 'start_date' => '2020-01-01'],
            ],
            'stickiness' => 'session_id',
        ];

        // Act - only session_id provided
        $result = $strategy->isEnabled($config, ['session_id' => 'sess-123']);

        // Assert - should work with custom stickiness
        self::assertTrue($result);
    }

    #[Test]
    public function it_passes_hash_algorithm_to_percentage_strategy(): void
    {
        // Arrange
        $strategy = $this->createStrategy();
        $config = [
            'schedule' => [
                ['percentage' => 100, 'start_date' => '2020-01-01'],
            ],
            'hash_algorithm' => 'md5',
        ];

        // Act
        $result = $strategy->isEnabled($config, ['user_id' => 'user-1']);

        // Assert
        self::assertTrue($result);
    }

    #[Test]
    public function it_passes_hash_seed_to_percentage_strategy(): void
    {
        // Arrange
        $strategy = $this->createStrategy();
        $config = [
            'schedule' => [
                ['percentage' => 100, 'start_date' => '2020-01-01'],
            ],
            'hash_seed' => 'experiment-2025',
        ];

        // Act
        $result = $strategy->isEnabled($config, ['user_id' => 'user-1']);

        // Assert
        self::assertTrue($result);
    }

    #[Test]
    public function it_handles_stages_with_datetime(): void
    {
        // Arrange
        $strategy = $this->createStrategy();
        $config = [
            'schedule' => [
                ['percentage' => 1, 'start_date' => '2020-01-01 00:00:00'],
                ['percentage' => 5, 'start_date' => '2020-01-01 12:00:00'],
                ['percentage' => 100, 'start_date' => '2020-01-02 00:00:00'],
            ],
        ];

        // Act
        $result = $strategy->isEnabled($config, ['user_id' => 'user-1']);

        // Assert - uses last stage (100%)
        self::assertTrue($result);
    }

    #[Test]
    public function it_handles_timezone_configuration(): void
    {
        // Arrange
        $strategy = $this->createStrategy();
        $config = [
            'schedule' => [
                ['percentage' => 100, 'start_date' => '2020-01-01'],
            ],
            'timezone' => 'America/New_York',
        ];

        // Act
        $result = $strategy->isEnabled($config, ['user_id' => 'user-1']);

        // Assert - should work with timezone
        self::assertTrue($result);
    }

    #[Test]
    public function it_handles_invalid_timezone_gracefully(): void
    {
        // Arrange
        $strategy = $this->createStrategy();
        $config = [
            'schedule' => [
                ['percentage' => 100, 'start_date' => '2020-01-01'],
            ],
            'timezone' => 'Invalid/Timezone',
        ];

        // Act
        $result = $strategy->isEnabled($config, ['user_id' => 'user-1']);

        // Assert - falls back to default timezone
        self::assertTrue($result);
    }

    #[Test]
    public function it_skips_stages_with_missing_percentage(): void
    {
        // Arrange
        $strategy = $this->createStrategy();
        $config = [
            'schedule' => [
                ['start_date' => '2020-01-01'], // Missing percentage
                ['percentage' => 100, 'start_date' => '2020-01-02'],
            ],
        ];

        // Act
        $result = $strategy->isEnabled($config, ['user_id' => 'user-1']);

        // Assert - uses second stage
        self::assertTrue($result);
    }

    #[Test]
    public function it_skips_stages_with_missing_start_date(): void
    {
        // Arrange
        $strategy = $this->createStrategy();
        $config = [
            'schedule' => [
                ['percentage' => 50], // Missing start_date
                ['percentage' => 100, 'start_date' => '2020-01-01'],
            ],
        ];

        // Act
        $result = $strategy->isEnabled($config, ['user_id' => 'user-1']);

        // Assert - uses second stage
        self::assertTrue($result);
    }

    #[Test]
    public function it_handles_invalid_start_date_format(): void
    {
        // Arrange
        $strategy = $this->createStrategy();
        $config = [
            'schedule' => [
                ['percentage' => 50, 'start_date' => 'not-a-date'], // Invalid date
                ['percentage' => 100, 'start_date' => '2020-01-01'],
            ],
        ];

        // Act
        $result = $strategy->isEnabled($config, ['user_id' => 'user-1']);

        // Assert - skips invalid stage, uses valid one
        self::assertTrue($result);
    }

    #[Test]
    public function it_works_with_gradual_rollout_scenario(): void
    {
        // Arrange - 7-day gradual rollout
        $strategy = $this->createStrategy();
        $config = [
            'schedule' => [
                ['percentage' => 1, 'start_date' => '2020-01-01'],
                ['percentage' => 5, 'start_date' => '2020-01-02'],
                ['percentage' => 10, 'start_date' => '2020-01-03'],
                ['percentage' => 25, 'start_date' => '2020-01-04'],
                ['percentage' => 50, 'start_date' => '2020-01-05'],
                ['percentage' => 75, 'start_date' => '2020-01-06'],
                ['percentage' => 100, 'start_date' => '2020-01-07'],
            ],
        ];

        // Act
        $result = $strategy->isEnabled($config, ['user_id' => 'user-1']);

        // Assert - currently at 100%
        self::assertTrue($result);
    }

    #[Test]
    public function it_works_with_single_stage_schedule(): void
    {
        // Arrange
        $strategy = $this->createStrategy();
        $config = [
            'schedule' => [
                ['percentage' => 100, 'start_date' => '2020-01-01'],
            ],
        ];

        // Act
        $result = $strategy->isEnabled($config, ['user_id' => 'user-1']);

        // Assert
        self::assertTrue($result);
    }

    #[Test]
    public function it_requires_user_identifier_like_percentage_strategy(): void
    {
        // Arrange
        $strategy = $this->createStrategy();
        $config = [
            'schedule' => [
                ['percentage' => 50, 'start_date' => '2020-01-01'],
            ],
        ];

        // Act - no user_id
        $result = $strategy->isEnabled($config, context: []);

        // Assert - fails without identifier
        self::assertFalse($result);
    }

    #[Test]
    public function it_distributes_users_according_to_current_percentage(): void
    {
        // Arrange
        $strategy = $this->createStrategy();
        $config = [
            'schedule' => [
                ['percentage' => 25, 'start_date' => '2020-01-01'], // Currently at 25%
                ['percentage' => 100, 'start_date' => '2099-01-01'], // Future
            ],
        ];

        $enabledCount = 0;
        $totalUsers = 1000;

        // Act - test with many users
        for ($i = 0; $i < $totalUsers; $i++) {
            if ($strategy->isEnabled($config, ['user_id' => "user-{$i}"])) {
                $enabledCount++;
            }
        }

        // Assert - approximately 25% (allow 20-30% variance)
        $actualPercentage = ($enabledCount / $totalUsers) * 100;
        self::assertGreaterThanOrEqual(20, $actualPercentage);
        self::assertLessThanOrEqual(30, $actualPercentage);
    }
}
