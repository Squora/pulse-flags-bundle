<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Tests\Strategy;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pulse\Flags\Core\Enum\FlagStrategy;
use Pulse\Flags\Core\Strategy\StrategyInterface;
use Pulse\Flags\Core\Strategy\UserIdStrategy;

final class UserIdStrategyTest extends TestCase
{
    #[Test]
    public function it_implements_strategy_interface(): void
    {
        // Arrange
        $strategy = new UserIdStrategy();

        // Act & Assert
        self::assertInstanceOf(StrategyInterface::class, $strategy);
    }

    #[Test]
    public function it_returns_correct_strategy_name(): void
    {
        // Arrange
        $strategy = new UserIdStrategy();

        // Act
        $name = $strategy->getName();

        // Assert
        self::assertSame('user_id', $name);
        self::assertSame(FlagStrategy::USER_ID->value, $name);
    }

    #[Test]
    public function it_returns_false_when_no_user_id_in_context(): void
    {
        // Arrange
        $strategy = new UserIdStrategy();
        $config = ['whitelist' => ['user-1', 'user-2']];

        // Act
        $result = $strategy->isEnabled($config, context: []);

        // Assert
        self::assertFalse($result);
    }

    #[Test]
    public function it_returns_false_when_user_id_is_null(): void
    {
        // Arrange
        $strategy = new UserIdStrategy();
        $config = ['whitelist' => ['user-1', 'user-2']];

        // Act
        $result = $strategy->isEnabled($config, context: ['user_id' => null]);

        // Assert
        self::assertFalse($result);
    }

    #[Test]
    public function it_returns_true_when_user_is_in_whitelist(): void
    {
        // Arrange
        $strategy = new UserIdStrategy();
        $config = ['whitelist' => ['user-1', 'user-2', 'user-3']];

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, context: ['user_id' => 'user-1']));
        self::assertTrue($strategy->isEnabled($config, context: ['user_id' => 'user-2']));
        self::assertTrue($strategy->isEnabled($config, context: ['user_id' => 'user-3']));
    }

    #[Test]
    public function it_returns_false_when_user_is_not_in_whitelist(): void
    {
        // Arrange
        $strategy = new UserIdStrategy();
        $config = ['whitelist' => ['user-1', 'user-2']];

        // Act & Assert
        self::assertFalse($strategy->isEnabled($config, context: ['user_id' => 'user-3']));
        self::assertFalse($strategy->isEnabled($config, context: ['user_id' => 'user-999']));
        self::assertFalse($strategy->isEnabled($config, context: ['user_id' => 'admin']));
    }

    #[Test]
    public function it_returns_false_when_user_is_in_blacklist(): void
    {
        // Arrange
        $strategy = new UserIdStrategy();
        $config = ['blacklist' => ['user-1', 'user-2', 'user-3']];

        // Act & Assert
        self::assertFalse($strategy->isEnabled($config, context: ['user_id' => 'user-1']));
        self::assertFalse($strategy->isEnabled($config, context: ['user_id' => 'user-2']));
        self::assertFalse($strategy->isEnabled($config, context: ['user_id' => 'user-3']));
    }

    #[Test]
    public function it_returns_true_when_user_is_not_in_blacklist(): void
    {
        // Arrange
        $strategy = new UserIdStrategy();
        $config = ['blacklist' => ['user-1', 'user-2']];

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, context: ['user_id' => 'user-3']));
        self::assertTrue($strategy->isEnabled($config, context: ['user_id' => 'user-999']));
        self::assertTrue($strategy->isEnabled($config, context: ['user_id' => 'admin']));
    }

    #[Test]
    public function it_returns_true_when_no_whitelist_or_blacklist(): void
    {
        // Arrange
        $strategy = new UserIdStrategy();
        $config = [];

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, context: ['user_id' => 'user-1']));
        self::assertTrue($strategy->isEnabled($config, context: ['user_id' => 'user-2']));
        self::assertTrue($strategy->isEnabled($config, context: ['user_id' => 'any-user']));
    }

    #[Test]
    public function it_prioritizes_whitelist_over_blacklist(): void
    {
        // Arrange
        $strategy = new UserIdStrategy();
        $config = [
            'whitelist' => ['user-1', 'user-2'],
            'blacklist' => ['user-1', 'user-3'], // Conflicting configuration
        ];

        // Act & Assert - whitelist takes precedence
        self::assertTrue($strategy->isEnabled($config, context: ['user_id' => 'user-1']));
        self::assertTrue($strategy->isEnabled($config, context: ['user_id' => 'user-2']));
        self::assertFalse($strategy->isEnabled($config, context: ['user_id' => 'user-3']));
        self::assertFalse($strategy->isEnabled($config, context: ['user_id' => 'user-999']));
    }

    #[Test]
    public function it_handles_empty_whitelist(): void
    {
        // Arrange
        $strategy = new UserIdStrategy();
        $config = ['whitelist' => []];

        // Act & Assert - empty whitelist is ignored, falls back to default (true)
        self::assertTrue($strategy->isEnabled($config, context: ['user_id' => 'user-1']));
    }

    #[Test]
    public function it_handles_empty_blacklist(): void
    {
        // Arrange
        $strategy = new UserIdStrategy();
        $config = ['blacklist' => []];

        // Act & Assert - empty blacklist is ignored, falls back to default (true)
        self::assertTrue($strategy->isEnabled($config, context: ['user_id' => 'user-1']));
    }

    #[Test]
    public function it_handles_integer_user_ids_in_whitelist(): void
    {
        // Arrange
        $strategy = new UserIdStrategy();
        $config = ['whitelist' => [123, 456, 789]];

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, context: ['user_id' => 123]));
        self::assertTrue($strategy->isEnabled($config, context: ['user_id' => 456]));
        self::assertFalse($strategy->isEnabled($config, context: ['user_id' => 999]));
    }

    #[Test]
    public function it_handles_integer_user_ids_in_blacklist(): void
    {
        // Arrange
        $strategy = new UserIdStrategy();
        $config = ['blacklist' => [123, 456]];

        // Act & Assert
        self::assertFalse($strategy->isEnabled($config, context: ['user_id' => 123]));
        self::assertFalse($strategy->isEnabled($config, context: ['user_id' => 456]));
        self::assertTrue($strategy->isEnabled($config, context: ['user_id' => 789]));
    }

    #[Test]
    public function it_handles_string_user_ids_in_whitelist(): void
    {
        // Arrange
        $strategy = new UserIdStrategy();
        $config = ['whitelist' => ['user-abc', 'user-def', 'admin@example.com']];

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, context: ['user_id' => 'user-abc']));
        self::assertTrue($strategy->isEnabled($config, context: ['user_id' => 'admin@example.com']));
        self::assertFalse($strategy->isEnabled($config, context: ['user_id' => 'user-xyz']));
    }

    #[Test]
    public function it_handles_mixed_type_user_ids(): void
    {
        // Arrange
        $strategy = new UserIdStrategy();
        $config = ['whitelist' => [123, 'user-abc', '456', 'admin']];

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, context: ['user_id' => 123]));
        self::assertTrue($strategy->isEnabled($config, context: ['user_id' => 'user-abc']));
        self::assertTrue($strategy->isEnabled($config, context: ['user_id' => '456']));
        self::assertTrue($strategy->isEnabled($config, context: ['user_id' => 'admin']));
    }

    #[Test]
    public function it_handles_single_user_in_whitelist(): void
    {
        // Arrange
        $strategy = new UserIdStrategy();
        $config = ['whitelist' => ['admin']];

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, context: ['user_id' => 'admin']));
        self::assertFalse($strategy->isEnabled($config, context: ['user_id' => 'user-1']));
    }

    #[Test]
    public function it_handles_single_user_in_blacklist(): void
    {
        // Arrange
        $strategy = new UserIdStrategy();
        $config = ['blacklist' => ['banned-user']];

        // Act & Assert
        self::assertFalse($strategy->isEnabled($config, context: ['user_id' => 'banned-user']));
        self::assertTrue($strategy->isEnabled($config, context: ['user_id' => 'user-1']));
    }

    #[Test]
    public function it_handles_large_whitelist(): void
    {
        // Arrange
        $strategy = new UserIdStrategy();
        $whitelist = range(1, 10000); // 10,000 users
        $config = ['whitelist' => $whitelist];

        // Act & Assert - should be efficient (O(1) lookup)
        self::assertTrue($strategy->isEnabled($config, context: ['user_id' => 1]));
        self::assertTrue($strategy->isEnabled($config, context: ['user_id' => 5000]));
        self::assertTrue($strategy->isEnabled($config, context: ['user_id' => 10000]));
        self::assertFalse($strategy->isEnabled($config, context: ['user_id' => 10001]));
    }

    #[Test]
    public function it_handles_large_blacklist(): void
    {
        // Arrange
        $strategy = new UserIdStrategy();
        $blacklist = range(1, 10000); // 10,000 users
        $config = ['blacklist' => $blacklist];

        // Act & Assert - should be efficient (O(1) lookup)
        self::assertFalse($strategy->isEnabled($config, context: ['user_id' => 1]));
        self::assertFalse($strategy->isEnabled($config, context: ['user_id' => 5000]));
        self::assertFalse($strategy->isEnabled($config, context: ['user_id' => 10000]));
        self::assertTrue($strategy->isEnabled($config, context: ['user_id' => 10001]));
    }

    #[Test]
    public function it_handles_uuid_user_ids(): void
    {
        // Arrange
        $strategy = new UserIdStrategy();
        $config = ['whitelist' => [
            '550e8400-e29b-41d4-a716-446655440000',
            '6ba7b810-9dad-11d1-80b4-00c04fd430c8',
        ]];

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, context: [
            'user_id' => '550e8400-e29b-41d4-a716-446655440000',
        ]));
        self::assertFalse($strategy->isEnabled($config, context: [
            'user_id' => 'ffffffff-ffff-ffff-ffff-ffffffffffff',
        ]));
    }

    #[Test]
    public function it_handles_email_as_user_ids(): void
    {
        // Arrange
        $strategy = new UserIdStrategy();
        $config = ['whitelist' => [
            'admin@example.com',
            'beta-tester@example.com',
        ]];

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, context: ['user_id' => 'admin@example.com']));
        self::assertTrue($strategy->isEnabled($config, context: ['user_id' => 'beta-tester@example.com']));
        self::assertFalse($strategy->isEnabled($config, context: ['user_id' => 'regular@example.com']));
    }

    #[Test]
    public function it_is_case_sensitive(): void
    {
        // Arrange
        $strategy = new UserIdStrategy();
        $config = ['whitelist' => ['User-1', 'ADMIN']];

        // Act & Assert - case matters
        self::assertTrue($strategy->isEnabled($config, context: ['user_id' => 'User-1']));
        self::assertTrue($strategy->isEnabled($config, context: ['user_id' => 'ADMIN']));
        self::assertFalse($strategy->isEnabled($config, context: ['user_id' => 'user-1']));
        self::assertFalse($strategy->isEnabled($config, context: ['user_id' => 'admin']));
    }

    #[Test]
    public function it_works_with_beta_testing_scenario(): void
    {
        // Arrange - enable feature only for beta testers
        $strategy = new UserIdStrategy();
        $config = ['whitelist' => ['beta-user-1', 'beta-user-2', 'internal-tester']];

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, context: ['user_id' => 'beta-user-1']));
        self::assertTrue($strategy->isEnabled($config, context: ['user_id' => 'internal-tester']));
        self::assertFalse($strategy->isEnabled($config, context: ['user_id' => 'regular-user']));
    }

    #[Test]
    public function it_works_with_banned_users_scenario(): void
    {
        // Arrange - enable for all except banned users
        $strategy = new UserIdStrategy();
        $config = ['blacklist' => ['banned-1', 'banned-2', 'abusive-user']];

        // Act & Assert
        self::assertFalse($strategy->isEnabled($config, context: ['user_id' => 'banned-1']));
        self::assertFalse($strategy->isEnabled($config, context: ['user_id' => 'abusive-user']));
        self::assertTrue($strategy->isEnabled($config, context: ['user_id' => 'regular-user']));
        self::assertTrue($strategy->isEnabled($config, context: ['user_id' => 'admin']));
    }

    #[Test]
    public function it_works_with_internal_testing_scenario(): void
    {
        // Arrange - enable only for company employees
        $strategy = new UserIdStrategy();
        $config = ['whitelist' => [
            'employee-1@company.com',
            'employee-2@company.com',
            'manager@company.com',
        ]];

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, context: ['user_id' => 'employee-1@company.com']));
        self::assertTrue($strategy->isEnabled($config, context: ['user_id' => 'manager@company.com']));
        self::assertFalse($strategy->isEnabled($config, context: ['user_id' => 'customer@external.com']));
    }

    #[Test]
    public function it_handles_context_with_additional_fields(): void
    {
        // Arrange
        $strategy = new UserIdStrategy();
        $config = ['whitelist' => ['user-1']];
        $context = [
            'user_id' => 'user-1',
            'session_id' => 'sess-abc',
            'ip' => '192.168.1.1',
            'country' => 'US',
        ];

        // Act
        $result = $strategy->isEnabled($config, $context);

        // Assert - ignores other context fields
        self::assertTrue($result);
    }
}
