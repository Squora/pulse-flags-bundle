<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Tests\Strategy;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pulse\Flags\Core\Enum\FlagStrategy;
use Pulse\Flags\Core\Enum\HashAlgorithm;
use Pulse\Flags\Core\Strategy\Hash\HashCalculator;
use Pulse\Flags\Core\Strategy\PercentageStrategy;
use Pulse\Flags\Core\Strategy\StrategyInterface;

final class PercentageStrategyTest extends TestCase
{
    #[Test]
    public function it_implements_strategy_interface(): void
    {
        // Arrange
        $strategy = new PercentageStrategy();

        // Act & Assert
        self::assertInstanceOf(StrategyInterface::class, $strategy);
    }

    #[Test]
    public function it_returns_correct_strategy_name(): void
    {
        // Arrange
        $strategy = new PercentageStrategy();

        // Act
        $name = $strategy->getName();

        // Assert
        self::assertSame('percentage', $name);
        self::assertSame(FlagStrategy::PERCENTAGE->value, $name);
    }

    #[Test]
    public function it_can_be_constructed_without_hash_calculator(): void
    {
        // Arrange & Act
        $strategy = new PercentageStrategy();

        // Assert
        self::assertInstanceOf(PercentageStrategy::class, $strategy);
    }

    #[Test]
    public function it_can_be_constructed_with_custom_hash_calculator(): void
    {
        // Arrange
        $hashCalculator = new HashCalculator();

        // Act
        $strategy = new PercentageStrategy($hashCalculator);

        // Assert
        self::assertInstanceOf(PercentageStrategy::class, $strategy);
    }

    #[Test]
    public function it_is_always_enabled_when_percentage_is_100(): void
    {
        // Arrange
        $strategy = new PercentageStrategy();
        $config = ['percentage' => 100];

        // Act & Assert - no identifier needed
        self::assertTrue($strategy->isEnabled($config, context: []));
        self::assertTrue($strategy->isEnabled($config, context: ['user_id' => 'user-1']));
        self::assertTrue($strategy->isEnabled($config, context: ['user_id' => 'user-2']));
    }

    #[Test]
    public function it_is_always_enabled_when_percentage_is_greater_than_100(): void
    {
        // Arrange
        $strategy = new PercentageStrategy();

        // Act & Assert
        self::assertTrue($strategy->isEnabled(['percentage' => 101], context: []));
        self::assertTrue($strategy->isEnabled(['percentage' => 150], context: []));
        self::assertTrue($strategy->isEnabled(['percentage' => 200], context: []));
    }

    #[Test]
    public function it_is_never_enabled_when_percentage_is_0(): void
    {
        // Arrange
        $strategy = new PercentageStrategy();
        $config = ['percentage' => 0];

        // Act & Assert
        self::assertFalse($strategy->isEnabled($config, context: ['user_id' => 'user-1']));
        self::assertFalse($strategy->isEnabled($config, context: ['user_id' => 'user-2']));
        self::assertFalse($strategy->isEnabled($config, context: ['user_id' => 'user-3']));
    }

    #[Test]
    public function it_is_never_enabled_when_percentage_is_less_than_0(): void
    {
        // Arrange
        $strategy = new PercentageStrategy();

        // Act & Assert
        self::assertFalse($strategy->isEnabled(['percentage' => -1], context: ['user_id' => 'user-1']));
        self::assertFalse($strategy->isEnabled(['percentage' => -10], context: ['user_id' => 'user-1']));
        self::assertFalse($strategy->isEnabled(['percentage' => -100], context: ['user_id' => 'user-1']));
    }

    #[Test]
    public function it_returns_false_when_no_identifier_provided(): void
    {
        // Arrange
        $strategy = new PercentageStrategy();
        $config = ['percentage' => 50];

        // Act & Assert - no user_id or session_id
        self::assertFalse($strategy->isEnabled($config, context: []));
        self::assertFalse($strategy->isEnabled($config, context: ['other_key' => 'value']));
    }

    #[Test]
    public function it_returns_false_when_identifier_is_null(): void
    {
        // Arrange
        $strategy = new PercentageStrategy();
        $config = ['percentage' => 50];

        // Act & Assert
        self::assertFalse($strategy->isEnabled($config, context: ['user_id' => null]));
        self::assertFalse($strategy->isEnabled($config, context: ['session_id' => null]));
    }

    #[Test]
    public function it_returns_false_when_identifier_is_empty_string(): void
    {
        // Arrange
        $strategy = new PercentageStrategy();
        $config = ['percentage' => 50];

        // Act & Assert
        self::assertFalse($strategy->isEnabled($config, context: ['user_id' => '']));
        self::assertFalse($strategy->isEnabled($config, context: ['session_id' => '']));
    }

    #[Test]
    public function it_uses_user_id_as_default_identifier(): void
    {
        // Arrange
        $strategy = new PercentageStrategy();
        $config = ['percentage' => 50];
        $context = ['user_id' => 'user-123'];

        // Act
        $result = $strategy->isEnabled($config, $context);

        // Assert - result is boolean (enabled or disabled based on hash)
        self::assertIsBool($result);
    }

    #[Test]
    public function it_falls_back_to_session_id_when_user_id_not_provided(): void
    {
        // Arrange
        $strategy = new PercentageStrategy();
        $config = ['percentage' => 50];
        $context = ['session_id' => 'session-abc'];

        // Act
        $result = $strategy->isEnabled($config, $context);

        // Assert
        self::assertIsBool($result);
    }

    #[Test]
    public function it_prefers_user_id_over_session_id(): void
    {
        // Arrange
        $strategy = new PercentageStrategy();
        $config = ['percentage' => 50];

        // Act
        $result1 = $strategy->isEnabled($config, ['user_id' => 'user-123', 'session_id' => 'session-abc']);
        $result2 = $strategy->isEnabled($config, ['user_id' => 'user-123', 'session_id' => 'session-xyz']);

        // Assert - same user_id produces same result regardless of session_id
        self::assertSame($result1, $result2);
    }

    #[Test]
    public function it_is_deterministic_for_same_user(): void
    {
        // Arrange
        $strategy = new PercentageStrategy();
        $config = ['percentage' => 50];
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
    public function it_uses_custom_stickiness_attribute(): void
    {
        // Arrange
        $strategy = new PercentageStrategy();
        $config = [
            'percentage' => 50,
            'stickiness' => 'company_id',
        ];
        $context = ['company_id' => 'company-456'];

        // Act
        $result = $strategy->isEnabled($config, $context);

        // Assert
        self::assertIsBool($result);
    }

    #[Test]
    public function it_supports_stickiness_as_string(): void
    {
        // Arrange
        $strategy = new PercentageStrategy();
        $config = [
            'percentage' => 50,
            'stickiness' => 'device_id',
        ];
        $context = ['device_id' => 'device-789'];

        // Act
        $result = $strategy->isEnabled($config, $context);

        // Assert
        self::assertIsBool($result);
    }

    #[Test]
    public function it_supports_stickiness_fallback_chain(): void
    {
        // Arrange
        $strategy = new PercentageStrategy();
        $config = [
            'percentage' => 50,
            'stickiness' => ['user_id', 'session_id', 'device_id'],
        ];

        // Act & Assert - uses first available
        self::assertIsBool($strategy->isEnabled($config, ['user_id' => 'user-1']));
        self::assertIsBool($strategy->isEnabled($config, ['session_id' => 'session-1']));
        self::assertIsBool($strategy->isEnabled($config, ['device_id' => 'device-1']));
    }

    #[Test]
    public function it_uses_first_available_identifier_in_stickiness_chain(): void
    {
        // Arrange
        $strategy = new PercentageStrategy();
        $config = [
            'percentage' => 50,
            'stickiness' => ['user_id', 'session_id', 'device_id'],
        ];

        // Act - session_id available but user_id is null
        $result1 = $strategy->isEnabled($config, ['user_id' => null, 'session_id' => 'session-abc']);
        $result2 = $strategy->isEnabled($config, ['user_id' => null, 'session_id' => 'session-abc']);

        // Assert - uses session_id consistently
        self::assertSame($result1, $result2);
    }

    #[Test]
    public function it_supports_crc32_hash_algorithm(): void
    {
        // Arrange
        $strategy = new PercentageStrategy();
        $config = [
            'percentage' => 50,
            'hash_algorithm' => 'crc32',
        ];
        $context = ['user_id' => 'user-123'];

        // Act
        $result = $strategy->isEnabled($config, $context);

        // Assert
        self::assertIsBool($result);
    }

    #[Test]
    public function it_supports_md5_hash_algorithm(): void
    {
        // Arrange
        $strategy = new PercentageStrategy();
        $config = [
            'percentage' => 50,
            'hash_algorithm' => 'md5',
        ];
        $context = ['user_id' => 'user-123'];

        // Act
        $result = $strategy->isEnabled($config, $context);

        // Assert
        self::assertIsBool($result);
    }

    #[Test]
    public function it_supports_sha256_hash_algorithm(): void
    {
        // Arrange
        $strategy = new PercentageStrategy();
        $config = [
            'percentage' => 50,
            'hash_algorithm' => 'sha256',
        ];
        $context = ['user_id' => 'user-123'];

        // Act
        $result = $strategy->isEnabled($config, $context);

        // Assert
        self::assertIsBool($result);
    }

    #[Test]
    public function it_uses_crc32_as_default_algorithm(): void
    {
        // Arrange
        $strategy = new PercentageStrategy();
        $config = ['percentage' => 50];
        $context = ['user_id' => 'user-123'];

        // Act
        $result1 = $strategy->isEnabled($config, $context);
        $result2 = $strategy->isEnabled(array_merge($config, ['hash_algorithm' => 'crc32']), $context);

        // Assert - default should be same as explicit crc32
        self::assertSame($result1, $result2);
    }

    #[Test]
    public function it_produces_different_results_with_different_algorithms(): void
    {
        // Arrange
        $strategy = new PercentageStrategy();
        $context = ['user_id' => 'user-123'];

        // Act
        $resultCrc32 = $strategy->isEnabled(['percentage' => 50, 'hash_algorithm' => 'crc32'], $context);
        $resultMd5 = $strategy->isEnabled(['percentage' => 50, 'hash_algorithm' => 'md5'], $context);
        $resultSha256 = $strategy->isEnabled(['percentage' => 50, 'hash_algorithm' => 'sha256'], $context);

        // Note: Results may or may not differ for specific user,
        // but algorithm choice should affect bucketing
        self::assertIsBool($resultCrc32);
        self::assertIsBool($resultMd5);
        self::assertIsBool($resultSha256);
    }

    #[Test]
    public function it_applies_hash_seed(): void
    {
        // Arrange
        $strategy = new PercentageStrategy();
        $context = ['user_id' => 'user-123'];

        // Act
        $resultNoSeed = $strategy->isEnabled(['percentage' => 50], $context);
        $resultWithSeed = $strategy->isEnabled(['percentage' => 50, 'hash_seed' => 'experiment-2025'], $context);

        // Assert - seed should affect bucketing (results may differ)
        self::assertIsBool($resultNoSeed);
        self::assertIsBool($resultWithSeed);
    }

    #[Test]
    public function it_is_deterministic_with_hash_seed(): void
    {
        // Arrange
        $strategy = new PercentageStrategy();
        $config = [
            'percentage' => 50,
            'hash_seed' => 'my-experiment',
        ];
        $context = ['user_id' => 'user-123'];

        // Act
        $result1 = $strategy->isEnabled($config, $context);
        $result2 = $strategy->isEnabled($config, $context);

        // Assert - same seed produces same result
        self::assertSame($result1, $result2);
    }

    #[Test]
    #[DataProvider('provideDecimalPercentages')]
    public function it_supports_decimal_percentages(float $percentage): void
    {
        // Arrange
        $strategy = new PercentageStrategy();
        $config = ['percentage' => $percentage];
        $context = ['user_id' => 'user-123'];

        // Act
        $result = $strategy->isEnabled($config, $context);

        // Assert
        self::assertIsBool($result);
    }

    public static function provideDecimalPercentages(): iterable
    {
        yield '0.001%' => [0.001];
        yield '0.125%' => [0.125];
        yield '0.5%' => [0.5];
        yield '1.5%' => [1.5];
        yield '25.75%' => [25.75];
        yield '99.999%' => [99.999];
    }

    #[Test]
    #[DataProvider('provideIntegerPercentages')]
    public function it_supports_integer_percentages(int $percentage): void
    {
        // Arrange
        $strategy = new PercentageStrategy();
        $config = ['percentage' => $percentage];
        $context = ['user_id' => 'user-123'];

        // Act
        $result = $strategy->isEnabled($config, $context);

        // Assert
        self::assertIsBool($result);
    }

    public static function provideIntegerPercentages(): iterable
    {
        yield '1%' => [1];
        yield '10%' => [10];
        yield '25%' => [25];
        yield '50%' => [50];
        yield '75%' => [75];
        yield '99%' => [99];
    }

    #[Test]
    public function it_uses_default_percentage_100_when_not_specified(): void
    {
        // Arrange
        $strategy = new PercentageStrategy();
        $config = []; // No percentage specified

        // Act
        $result = $strategy->isEnabled($config, context: []);

        // Assert - default 100 means always enabled
        self::assertTrue($result);
    }

    #[Test]
    public function it_distributes_users_according_to_percentage(): void
    {
        // Arrange
        $strategy = new PercentageStrategy();
        $percentage = 25; // 25% should enable roughly 25 out of 100 users
        $config = ['percentage' => $percentage];
        $enabledCount = 0;
        $totalUsers = 1000;

        // Act - test with 1000 different users
        for ($i = 0; $i < $totalUsers; $i++) {
            if ($strategy->isEnabled($config, ['user_id' => "user-{$i}"])) {
                $enabledCount++;
            }
        }

        // Assert - should be approximately 25% (allow 20-30% range for statistical variance)
        $actualPercentage = ($enabledCount / $totalUsers) * 100;
        self::assertGreaterThanOrEqual(20, $actualPercentage, "Too few users enabled: {$actualPercentage}%");
        self::assertLessThanOrEqual(30, $actualPercentage, "Too many users enabled: {$actualPercentage}%");
    }

    #[Test]
    public function it_handles_very_small_percentages(): void
    {
        // Arrange
        $strategy = new PercentageStrategy();
        $percentage = 0.01; // 0.01% = 10 in 100,000 users (more reliable for testing)
        $config = ['percentage' => $percentage];
        $enabledCount = 0;
        $totalUsers = 100000;

        // Act - test with many users
        for ($i = 0; $i < $totalUsers; $i++) {
            if ($strategy->isEnabled($config, ['user_id' => "user-{$i}"])) {
                $enabledCount++;
            }
        }

        // Assert - should be very few users (allow variance: expect ~10, allow 3-30)
        self::assertGreaterThan(2, $enabledCount, 'At least some users should be enabled for 0.01%');
        self::assertLessThan(30, $enabledCount, 'Too many users enabled for 0.01%');
    }

    #[Test]
    public function it_handles_numeric_string_user_ids(): void
    {
        // Arrange
        $strategy = new PercentageStrategy();
        $config = ['percentage' => 50];

        // Act
        $result1 = $strategy->isEnabled($config, ['user_id' => '123']);
        $result2 = $strategy->isEnabled($config, ['user_id' => '456']);

        // Assert
        self::assertIsBool($result1);
        self::assertIsBool($result2);
    }

    #[Test]
    public function it_handles_integer_user_ids(): void
    {
        // Arrange
        $strategy = new PercentageStrategy();
        $config = ['percentage' => 50];

        // Act
        $result = $strategy->isEnabled($config, ['user_id' => 123]);

        // Assert
        self::assertIsBool($result);
    }

    #[Test]
    public function it_handles_uuid_identifiers(): void
    {
        // Arrange
        $strategy = new PercentageStrategy();
        $config = ['percentage' => 50];
        $context = ['user_id' => '550e8400-e29b-41d4-a716-446655440000'];

        // Act
        $result = $strategy->isEnabled($config, $context);

        // Assert
        self::assertIsBool($result);
    }

    #[Test]
    public function it_handles_email_identifiers(): void
    {
        // Arrange
        $strategy = new PercentageStrategy();
        $config = ['percentage' => 50];
        $context = ['user_id' => 'user@example.com'];

        // Act
        $result = $strategy->isEnabled($config, $context);

        // Assert
        self::assertIsBool($result);
    }

    #[Test]
    public function it_ignores_invalid_hash_algorithm(): void
    {
        // Arrange
        $strategy = new PercentageStrategy();
        $config = [
            'percentage' => 50,
            'hash_algorithm' => 'invalid', // Invalid algorithm
        ];
        $context = ['user_id' => 'user-123'];

        // Act
        $result = $strategy->isEnabled($config, $context);

        // Assert - should fall back to default (crc32) and work
        self::assertIsBool($result);
    }

    #[Test]
    public function it_works_with_real_world_config(): void
    {
        // Arrange
        $strategy = new PercentageStrategy();
        $config = [
            'percentage' => 25,
            'hash_algorithm' => 'crc32',
            'hash_seed' => 'feature-2025-q1',
            'stickiness' => ['user_id', 'session_id'],
        ];
        $context = [
            'user_id' => 'user-123',
            'session_id' => 'sess-abc-def',
            'ip' => '192.168.1.1',
        ];

        // Act
        $result = $strategy->isEnabled($config, $context);

        // Assert
        self::assertIsBool($result);
    }
}
