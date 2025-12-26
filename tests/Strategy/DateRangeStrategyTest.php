<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Tests\Strategy;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pulse\Flags\Core\Enum\FlagStrategy;
use Pulse\Flags\Core\Strategy\DateRangeStrategy;
use Pulse\Flags\Core\Strategy\StrategyInterface;

final class DateRangeStrategyTest extends TestCase
{
    #[Test]
    public function it_implements_strategy_interface(): void
    {
        // Arrange
        $strategy = new DateRangeStrategy();

        // Act & Assert
        self::assertInstanceOf(StrategyInterface::class, $strategy);
    }

    #[Test]
    public function it_returns_correct_strategy_name(): void
    {
        // Arrange
        $strategy = new DateRangeStrategy();

        // Act
        $name = $strategy->getName();

        // Assert
        self::assertSame('date_range', $name);
        self::assertSame(FlagStrategy::DATE_RANGE->value, $name);
    }

    #[Test]
    public function it_is_enabled_when_current_date_is_within_range(): void
    {
        // Arrange
        $strategy = new DateRangeStrategy();
        $config = [
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
        ];
        $context = ['current_date' => '2025-06-15']; // Middle of the year

        // Act
        $result = $strategy->isEnabled($config, $context);

        // Assert
        self::assertTrue($result);
    }

    #[Test]
    public function it_is_disabled_when_current_date_is_before_start_date(): void
    {
        // Arrange
        $strategy = new DateRangeStrategy();
        $config = [
            'start_date' => '2025-06-01',
            'end_date' => '2025-12-31',
        ];
        $context = ['current_date' => '2025-05-31']; // One day before

        // Act
        $result = $strategy->isEnabled($config, $context);

        // Assert
        self::assertFalse($result);
    }

    #[Test]
    public function it_is_disabled_when_current_date_is_after_end_date(): void
    {
        // Arrange
        $strategy = new DateRangeStrategy();
        $config = [
            'start_date' => '2025-01-01',
            'end_date' => '2025-06-30',
        ];
        $context = ['current_date' => '2025-07-01']; // One day after

        // Act
        $result = $strategy->isEnabled($config, $context);

        // Assert
        self::assertFalse($result);
    }

    #[Test]
    public function it_is_enabled_when_current_date_equals_start_date(): void
    {
        // Arrange
        $strategy = new DateRangeStrategy();
        $config = [
            'start_date' => '2025-06-01',
            'end_date' => '2025-06-30',
        ];
        $context = ['current_date' => '2025-06-01 14:30:00']; // Same day, afternoon

        // Act
        $result = $strategy->isEnabled($config, $context);

        // Assert - inclusive boundary
        self::assertTrue($result);
    }

    #[Test]
    public function it_is_enabled_when_current_date_equals_end_date(): void
    {
        // Arrange
        $strategy = new DateRangeStrategy();
        $config = [
            'start_date' => '2025-06-01',
            'end_date' => '2025-06-30',
        ];
        $context = ['current_date' => '2025-06-30 14:30:00']; // Last day, afternoon

        // Act
        $result = $strategy->isEnabled($config, $context);

        // Assert - inclusive boundary
        self::assertTrue($result);
    }

    #[Test]
    public function it_handles_start_date_at_beginning_of_day(): void
    {
        // Arrange
        $strategy = new DateRangeStrategy();
        $config = ['start_date' => '2025-06-01'];
        $context = ['current_date' => '2025-06-01 00:00:00']; // Midnight

        // Act
        $result = $strategy->isEnabled($config, $context);

        // Assert - should be enabled at midnight
        self::assertTrue($result);
    }

    #[Test]
    public function it_handles_end_date_at_end_of_day(): void
    {
        // Arrange
        $strategy = new DateRangeStrategy();
        $config = ['end_date' => '2025-06-30'];
        $context = ['current_date' => '2025-06-30 23:59:59']; // Last second of day

        // Act
        $result = $strategy->isEnabled($config, $context);

        // Assert - should be enabled until end of day
        self::assertTrue($result);
    }

    #[Test]
    public function it_is_enabled_with_only_start_date_when_current_date_is_after(): void
    {
        // Arrange
        $strategy = new DateRangeStrategy();
        $config = ['start_date' => '2025-01-01']; // No end_date

        // Act & Assert - enabled from start_date onwards
        self::assertTrue($strategy->isEnabled($config, ['current_date' => '2025-01-01']));
        self::assertTrue($strategy->isEnabled($config, ['current_date' => '2025-06-15']));
        self::assertTrue($strategy->isEnabled($config, ['current_date' => '2025-12-31']));
        self::assertTrue($strategy->isEnabled($config, ['current_date' => '2026-01-01']));
    }

    #[Test]
    public function it_is_disabled_with_only_start_date_when_current_date_is_before(): void
    {
        // Arrange
        $strategy = new DateRangeStrategy();
        $config = ['start_date' => '2025-06-01']; // No end_date

        // Act
        $result = $strategy->isEnabled($config, ['current_date' => '2025-05-31']);

        // Assert
        self::assertFalse($result);
    }

    #[Test]
    public function it_is_enabled_with_only_end_date_when_current_date_is_before(): void
    {
        // Arrange
        $strategy = new DateRangeStrategy();
        $config = ['end_date' => '2025-12-31']; // No start_date

        // Act & Assert - enabled until end_date
        self::assertTrue($strategy->isEnabled($config, ['current_date' => '2024-01-01']));
        self::assertTrue($strategy->isEnabled($config, ['current_date' => '2025-06-15']));
        self::assertTrue($strategy->isEnabled($config, ['current_date' => '2025-12-31']));
    }

    #[Test]
    public function it_is_disabled_with_only_end_date_when_current_date_is_after(): void
    {
        // Arrange
        $strategy = new DateRangeStrategy();
        $config = ['end_date' => '2025-06-30']; // No start_date

        // Act
        $result = $strategy->isEnabled($config, ['current_date' => '2025-07-01']);

        // Assert
        self::assertFalse($result);
    }

    #[Test]
    public function it_is_always_enabled_without_start_date_or_end_date(): void
    {
        // Arrange
        $strategy = new DateRangeStrategy();
        $config = []; // No date restrictions

        // Act & Assert - always enabled
        self::assertTrue($strategy->isEnabled($config, ['current_date' => '2020-01-01']));
        self::assertTrue($strategy->isEnabled($config, ['current_date' => '2025-06-15']));
        self::assertTrue($strategy->isEnabled($config, ['current_date' => '2030-12-31']));
    }

    #[Test]
    public function it_uses_current_date_from_now_when_not_provided_in_context(): void
    {
        // Arrange
        $strategy = new DateRangeStrategy();
        $config = []; // No date restrictions

        // Act
        $result = $strategy->isEnabled($config, context: []);

        // Assert - should use current time and be enabled
        self::assertTrue($result);
    }

    #[Test]
    public function it_handles_current_date_as_datetime_object(): void
    {
        // Arrange
        $strategy = new DateRangeStrategy();
        $config = [
            'start_date' => '2025-06-01',
            'end_date' => '2025-06-30',
        ];
        $currentDate = new \DateTimeImmutable('2025-06-15');

        // Act
        $result = $strategy->isEnabled($config, ['current_date' => $currentDate]);

        // Assert
        self::assertTrue($result);
    }

    #[Test]
    public function it_handles_start_date_as_datetime_object(): void
    {
        // Arrange
        $strategy = new DateRangeStrategy();
        $startDate = new \DateTimeImmutable('2025-06-01');
        $config = ['start_date' => $startDate];

        // Act
        $result = $strategy->isEnabled($config, ['current_date' => '2025-06-15']);

        // Assert
        self::assertTrue($result);
    }

    #[Test]
    public function it_handles_end_date_as_datetime_object(): void
    {
        // Arrange
        $strategy = new DateRangeStrategy();
        $endDate = new \DateTimeImmutable('2025-06-30');
        $config = ['end_date' => $endDate];

        // Act
        $result = $strategy->isEnabled($config, ['current_date' => '2025-06-15']);

        // Assert
        self::assertTrue($result);
    }

    #[Test]
    public function it_handles_all_dates_as_datetime_objects(): void
    {
        // Arrange
        $strategy = new DateRangeStrategy();
        $config = [
            'start_date' => new \DateTimeImmutable('2025-06-01'),
            'end_date' => new \DateTimeImmutable('2025-06-30'),
        ];
        $context = ['current_date' => new \DateTimeImmutable('2025-06-15')];

        // Act
        $result = $strategy->isEnabled($config, $context);

        // Assert
        self::assertTrue($result);
    }

    #[Test]
    public function it_returns_false_for_invalid_start_date_format(): void
    {
        // Arrange
        $strategy = new DateRangeStrategy();
        $config = ['start_date' => 'invalid-date'];

        // Act
        $result = $strategy->isEnabled($config, ['current_date' => '2025-06-15']);

        // Assert - fail-safe behavior
        self::assertFalse($result);
    }

    #[Test]
    public function it_returns_false_for_invalid_end_date_format(): void
    {
        // Arrange
        $strategy = new DateRangeStrategy();
        $config = ['end_date' => 'invalid-date'];

        // Act
        $result = $strategy->isEnabled($config, ['current_date' => '2025-06-15']);

        // Assert - fail-safe behavior
        self::assertFalse($result);
    }

    #[Test]
    public function it_returns_false_for_invalid_current_date_format(): void
    {
        // Arrange
        $strategy = new DateRangeStrategy();
        $config = [
            'start_date' => '2025-06-01',
            'end_date' => '2025-06-30',
        ];

        // Act
        $result = $strategy->isEnabled($config, ['current_date' => 'not-a-date']);

        // Assert - fail-safe behavior
        self::assertFalse($result);
    }

    #[Test]
    public function it_handles_different_date_formats(): void
    {
        // Arrange
        $strategy = new DateRangeStrategy();

        // Act & Assert - various valid formats
        self::assertTrue($strategy->isEnabled(
            ['start_date' => '2025-06-01', 'end_date' => '2025-06-30'],
            ['current_date' => '2025-06-15']
        ));

        self::assertTrue($strategy->isEnabled(
            ['start_date' => '2025-06-01 00:00:00', 'end_date' => '2025-06-30 23:59:59'],
            ['current_date' => '2025-06-15 12:00:00']
        ));

        self::assertTrue($strategy->isEnabled(
            ['start_date' => '01-06-2025', 'end_date' => '30-06-2025'],
            ['current_date' => '15-06-2025']
        ));
    }

    #[Test]
    public function it_handles_timezone_configuration(): void
    {
        // Arrange
        $strategy = new DateRangeStrategy();
        $config = [
            'start_date' => '2025-06-01',
            'end_date' => '2025-06-30',
            'timezone' => 'America/New_York',
        ];

        // Act - use date that might be different in different timezones
        $result = $strategy->isEnabled($config, ['current_date' => '2025-06-15 12:00:00']);

        // Assert - should work with timezone
        self::assertTrue($result);
    }

    #[Test]
    public function it_works_with_time_limited_promotion_scenario(): void
    {
        // Arrange - Black Friday promotion
        $strategy = new DateRangeStrategy();
        $config = [
            'start_date' => '2025-11-28', // Black Friday
            'end_date' => '2025-11-30',   // Cyber Monday
        ];

        // Act & Assert
        self::assertFalse($strategy->isEnabled($config, ['current_date' => '2025-11-27']));
        self::assertTrue($strategy->isEnabled($config, ['current_date' => '2025-11-28']));
        self::assertTrue($strategy->isEnabled($config, ['current_date' => '2025-11-29']));
        self::assertTrue($strategy->isEnabled($config, ['current_date' => '2025-11-30']));
        self::assertFalse($strategy->isEnabled($config, ['current_date' => '2025-12-01']));
    }

    #[Test]
    public function it_works_with_scheduled_feature_release_scenario(): void
    {
        // Arrange - Feature enabled from specific date onwards
        $strategy = new DateRangeStrategy();
        $config = [
            'start_date' => '2025-07-01', // Release date
            // No end_date - permanent after release
        ];

        // Act & Assert
        self::assertFalse($strategy->isEnabled($config, ['current_date' => '2025-06-30']));
        self::assertTrue($strategy->isEnabled($config, ['current_date' => '2025-07-01']));
        self::assertTrue($strategy->isEnabled($config, ['current_date' => '2025-12-31']));
        self::assertTrue($strategy->isEnabled($config, ['current_date' => '2026-01-01']));
    }

    #[Test]
    public function it_works_with_seasonal_feature_scenario(): void
    {
        // Arrange - Christmas theme active in December
        $strategy = new DateRangeStrategy();
        $config = [
            'start_date' => '2025-12-01',
            'end_date' => '2025-12-31',
        ];

        // Act & Assert
        self::assertFalse($strategy->isEnabled($config, ['current_date' => '2025-11-30']));
        self::assertTrue($strategy->isEnabled($config, ['current_date' => '2025-12-01']));
        self::assertTrue($strategy->isEnabled($config, ['current_date' => '2025-12-25']));
        self::assertTrue($strategy->isEnabled($config, ['current_date' => '2025-12-31']));
        self::assertFalse($strategy->isEnabled($config, ['current_date' => '2026-01-01']));
    }

    #[Test]
    public function it_works_with_feature_sunset_scenario(): void
    {
        // Arrange - Old feature disabled after deprecation date
        $strategy = new DateRangeStrategy();
        $config = [
            // No start_date - was enabled in the past
            'end_date' => '2025-06-30', // Deprecation/sunset date
        ];

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, ['current_date' => '2025-01-01']));
        self::assertTrue($strategy->isEnabled($config, ['current_date' => '2025-06-30']));
        self::assertFalse($strategy->isEnabled($config, ['current_date' => '2025-07-01']));
    }

    #[Test]
    public function it_handles_leap_year_dates(): void
    {
        // Arrange
        $strategy = new DateRangeStrategy();
        $config = [
            'start_date' => '2024-02-28',
            'end_date' => '2024-03-01',
        ];

        // Act & Assert - February 29th exists in 2024 (leap year)
        self::assertTrue($strategy->isEnabled($config, ['current_date' => '2024-02-28']));
        self::assertTrue($strategy->isEnabled($config, ['current_date' => '2024-02-29']));
        self::assertTrue($strategy->isEnabled($config, ['current_date' => '2024-03-01']));
    }

    #[Test]
    public function it_handles_year_boundary_crossing(): void
    {
        // Arrange - New Year promotion spanning year boundary
        $strategy = new DateRangeStrategy();
        $config = [
            'start_date' => '2024-12-30',
            'end_date' => '2025-01-02',
        ];

        // Act & Assert
        self::assertFalse($strategy->isEnabled($config, ['current_date' => '2024-12-29']));
        self::assertTrue($strategy->isEnabled($config, ['current_date' => '2024-12-30']));
        self::assertTrue($strategy->isEnabled($config, ['current_date' => '2024-12-31']));
        self::assertTrue($strategy->isEnabled($config, ['current_date' => '2025-01-01']));
        self::assertTrue($strategy->isEnabled($config, ['current_date' => '2025-01-02']));
        self::assertFalse($strategy->isEnabled($config, ['current_date' => '2025-01-03']));
    }
}
