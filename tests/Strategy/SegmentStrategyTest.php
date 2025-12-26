<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Tests\Strategy;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pulse\Flags\Core\Enum\FlagStrategy;
use Pulse\Flags\Core\Segment\SegmentInterface;
use Pulse\Flags\Core\Segment\SegmentRepository;
use Pulse\Flags\Core\Segment\StaticSegment;
use Pulse\Flags\Core\Strategy\SegmentStrategy;
use Pulse\Flags\Core\Strategy\StrategyInterface;

final class SegmentStrategyTest extends TestCase
{
    private function createSegmentRepository(): SegmentRepository
    {
        $repository = new SegmentRepository();

        // Add some test segments
        $repository->add(new StaticSegment('premium_users', ['user-1', 'user-2', 'user-3']));
        $repository->add(new StaticSegment('beta_testers', ['user-10', 'user-20', 'user-30']));
        $repository->add(new StaticSegment('internal_team', ['admin-1', 'admin-2']));

        return $repository;
    }

    #[Test]
    public function it_implements_strategy_interface(): void
    {
        // Arrange
        $repository = new SegmentRepository();
        $strategy = new SegmentStrategy($repository);

        // Act & Assert
        self::assertInstanceOf(StrategyInterface::class, $strategy);
    }

    #[Test]
    public function it_returns_correct_strategy_name(): void
    {
        // Arrange
        $repository = new SegmentRepository();
        $strategy = new SegmentStrategy($repository);

        // Act
        $name = $strategy->getName();

        // Assert
        self::assertSame('segment', $name);
        self::assertSame(FlagStrategy::SEGMENT->value, $name);
    }

    #[Test]
    public function it_is_enabled_when_user_is_in_segment(): void
    {
        // Arrange
        $repository = $this->createSegmentRepository();
        $strategy = new SegmentStrategy($repository);
        $config = ['segments' => ['premium_users']];
        $context = ['user_id' => 'user-1'];

        // Act
        $result = $strategy->isEnabled($config, $context);

        // Assert
        self::assertTrue($result);
    }

    #[Test]
    public function it_is_disabled_when_user_is_not_in_segment(): void
    {
        // Arrange
        $repository = $this->createSegmentRepository();
        $strategy = new SegmentStrategy($repository);
        $config = ['segments' => ['premium_users']];
        $context = ['user_id' => 'user-999'];

        // Act
        $result = $strategy->isEnabled($config, $context);

        // Assert
        self::assertFalse($result);
    }

    #[Test]
    public function it_uses_or_logic_for_multiple_segments(): void
    {
        // Arrange - user needs to be in ANY segment
        $repository = $this->createSegmentRepository();
        $strategy = new SegmentStrategy($repository);
        $config = ['segments' => ['premium_users', 'beta_testers']];

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, ['user_id' => 'user-1'])); // In premium_users
        self::assertTrue($strategy->isEnabled($config, ['user_id' => 'user-10'])); // In beta_testers
        self::assertFalse($strategy->isEnabled($config, ['user_id' => 'user-999'])); // In neither
    }

    #[Test]
    public function it_returns_false_when_user_id_is_missing(): void
    {
        // Arrange
        $repository = $this->createSegmentRepository();
        $strategy = new SegmentStrategy($repository);
        $config = ['segments' => ['premium_users']];

        // Act & Assert
        self::assertFalse($strategy->isEnabled($config, context: []));
        self::assertFalse($strategy->isEnabled($config, ['user_id' => null]));
    }

    #[Test]
    public function it_returns_false_when_no_segments_configured(): void
    {
        // Arrange
        $repository = $this->createSegmentRepository();
        $strategy = new SegmentStrategy($repository);
        $config = ['segments' => []];

        // Act
        $result = $strategy->isEnabled($config, ['user_id' => 'user-1']);

        // Assert
        self::assertFalse($result);
    }

    #[Test]
    public function it_returns_false_when_segments_key_missing(): void
    {
        // Arrange
        $repository = $this->createSegmentRepository();
        $strategy = new SegmentStrategy($repository);
        $config = [];

        // Act
        $result = $strategy->isEnabled($config, ['user_id' => 'user-1']);

        // Assert
        self::assertFalse($result);
    }

    #[Test]
    public function it_handles_non_existent_segment_gracefully(): void
    {
        // Arrange
        $repository = $this->createSegmentRepository();
        $strategy = new SegmentStrategy($repository);
        $config = ['segments' => ['non_existent_segment']];

        // Act
        $result = $strategy->isEnabled($config, ['user_id' => 'user-1']);

        // Assert - returns false when segment doesn't exist
        self::assertFalse($result);
    }

    #[Test]
    public function it_skips_non_existent_segments_but_checks_others(): void
    {
        // Arrange
        $repository = $this->createSegmentRepository();
        $strategy = new SegmentStrategy($repository);
        $config = ['segments' => ['non_existent', 'premium_users']]; // One doesn't exist

        // Act
        $result = $strategy->isEnabled($config, ['user_id' => 'user-1']);

        // Assert - still matches premium_users
        self::assertTrue($result);
    }

    #[Test]
    public function it_works_with_integer_user_ids(): void
    {
        // Arrange
        $repository = new SegmentRepository();
        $repository->add(new StaticSegment('users', [123, 456, 789]));
        $strategy = new SegmentStrategy($repository);
        $config = ['segments' => ['users']];

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, ['user_id' => 123]));
        self::assertTrue($strategy->isEnabled($config, ['user_id' => 456]));
        self::assertFalse($strategy->isEnabled($config, ['user_id' => 999]));
    }

    #[Test]
    public function it_works_with_string_user_ids(): void
    {
        // Arrange
        $repository = new SegmentRepository();
        $repository->add(new StaticSegment('users', ['abc', 'def', 'ghi']));
        $strategy = new SegmentStrategy($repository);
        $config = ['segments' => ['users']];

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, ['user_id' => 'abc']));
        self::assertTrue($strategy->isEnabled($config, ['user_id' => 'def']));
        self::assertFalse($strategy->isEnabled($config, ['user_id' => 'xyz']));
    }

    #[Test]
    public function it_works_with_mixed_user_id_types(): void
    {
        // Arrange
        $repository = new SegmentRepository();
        $repository->add(new StaticSegment('users', [123, 'user-abc', '456']));
        $strategy = new SegmentStrategy($repository);
        $config = ['segments' => ['users']];

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, ['user_id' => 123]));
        self::assertTrue($strategy->isEnabled($config, ['user_id' => 'user-abc']));
        self::assertTrue($strategy->isEnabled($config, ['user_id' => '456']));
    }

    #[Test]
    public function it_works_with_beta_testing_scenario(): void
    {
        // Arrange - Enable for beta testers and internal team
        $repository = $this->createSegmentRepository();
        $strategy = new SegmentStrategy($repository);
        $config = ['segments' => ['beta_testers', 'internal_team']];

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, ['user_id' => 'user-10'])); // Beta tester
        self::assertTrue($strategy->isEnabled($config, ['user_id' => 'admin-1'])); // Internal team
        self::assertFalse($strategy->isEnabled($config, ['user_id' => 'user-1'])); // Regular user
    }

    #[Test]
    public function it_works_with_premium_feature_scenario(): void
    {
        // Arrange - Feature only for premium users
        $repository = $this->createSegmentRepository();
        $strategy = new SegmentStrategy($repository);
        $config = ['segments' => ['premium_users']];

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, ['user_id' => 'user-1']));
        self::assertTrue($strategy->isEnabled($config, ['user_id' => 'user-2']));
        self::assertTrue($strategy->isEnabled($config, ['user_id' => 'user-3']));
        self::assertFalse($strategy->isEnabled($config, ['user_id' => 'user-999']));
    }

    #[Test]
    public function it_works_with_single_segment(): void
    {
        // Arrange
        $repository = $this->createSegmentRepository();
        $strategy = new SegmentStrategy($repository);
        $config = ['segments' => ['internal_team']];

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, ['user_id' => 'admin-1']));
        self::assertTrue($strategy->isEnabled($config, ['user_id' => 'admin-2']));
        self::assertFalse($strategy->isEnabled($config, ['user_id' => 'user-1']));
    }

    #[Test]
    public function it_works_with_three_segments(): void
    {
        // Arrange
        $repository = $this->createSegmentRepository();
        $strategy = new SegmentStrategy($repository);
        $config = ['segments' => ['premium_users', 'beta_testers', 'internal_team']];

        // Act & Assert - user in any of the three segments
        self::assertTrue($strategy->isEnabled($config, ['user_id' => 'user-1']));  // Premium
        self::assertTrue($strategy->isEnabled($config, ['user_id' => 'user-10'])); // Beta
        self::assertTrue($strategy->isEnabled($config, ['user_id' => 'admin-1'])); // Internal
        self::assertFalse($strategy->isEnabled($config, ['user_id' => 'user-999'])); // None
    }

    #[Test]
    public function it_handles_empty_segment(): void
    {
        // Arrange - segment with no users
        $repository = new SegmentRepository();
        $repository->add(new StaticSegment('empty_segment', []));
        $strategy = new SegmentStrategy($repository);
        $config = ['segments' => ['empty_segment']];

        // Act
        $result = $strategy->isEnabled($config, ['user_id' => 'user-1']);

        // Assert
        self::assertFalse($result);
    }

    #[Test]
    public function it_handles_large_segment(): void
    {
        // Arrange - segment with many users
        $largeUserList = range(1, 10000);
        $repository = new SegmentRepository();
        $repository->add(new StaticSegment('large_segment', $largeUserList));
        $strategy = new SegmentStrategy($repository);
        $config = ['segments' => ['large_segment']];

        // Act & Assert - should be efficient (O(1) lookup)
        self::assertTrue($strategy->isEnabled($config, ['user_id' => 1]));
        self::assertTrue($strategy->isEnabled($config, ['user_id' => 5000]));
        self::assertTrue($strategy->isEnabled($config, ['user_id' => 10000]));
        self::assertFalse($strategy->isEnabled($config, ['user_id' => 10001]));
    }

    #[Test]
    public function it_handles_context_with_additional_fields(): void
    {
        // Arrange
        $repository = $this->createSegmentRepository();
        $strategy = new SegmentStrategy($repository);
        $config = ['segments' => ['premium_users']];
        $context = [
            'user_id' => 'user-1',
            'session_id' => 'sess-abc',
            'ip' => '192.168.1.1',
            'country' => 'US',
        ];

        // Act
        $result = $strategy->isEnabled($config, $context);

        // Assert - ignores extra context fields for static segments
        self::assertTrue($result);
    }

    #[Test]
    public function it_uses_custom_segment_implementation(): void
    {
        // Arrange - custom segment mock
        $customSegment = new class implements SegmentInterface {
            public function contains(string|int $userId, array $context = []): bool
            {
                // Custom logic: only users with 'admin' prefix
                return is_string($userId) && str_starts_with($userId, 'admin');
            }

            public function getName(): string
            {
                return 'custom_segment';
            }

            public function getType(): string
            {
                return 'custom';
            }
        };

        $repository = new SegmentRepository();
        $repository->add($customSegment);
        $strategy = new SegmentStrategy($repository);
        $config = ['segments' => ['custom_segment']];

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, ['user_id' => 'admin-123']));
        self::assertFalse($strategy->isEnabled($config, ['user_id' => 'user-123']));
    }
}
