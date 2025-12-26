<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Tests\Context;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pulse\Flags\Core\Context\ContextInterface;
use Pulse\Flags\Core\Context\UserContext;

final class UserContextTest extends TestCase
{
    #[Test]
    public function it_can_be_created_with_user_id_only(): void
    {
        // Arrange
        $userId = 'user-123';

        // Act
        $context = new UserContext(userId: $userId);

        // Assert
        self::assertInstanceOf(UserContext::class, $context);
        self::assertSame($userId, $context->getUserId());
        self::assertNull($context->getSessionId());
        self::assertNull($context->getCompanyId());
    }

    #[Test]
    public function it_can_be_created_with_user_id_and_session_id(): void
    {
        // Arrange
        $userId = 'user-123';
        $sessionId = 'session-456';

        // Act
        $context = new UserContext(
            userId: $userId,
            sessionId: $sessionId
        );

        // Assert
        self::assertSame($userId, $context->getUserId());
        self::assertSame($sessionId, $context->getSessionId());
        self::assertNull($context->getCompanyId());
    }

    #[Test]
    public function it_can_be_created_with_all_parameters(): void
    {
        // Arrange
        $userId = 'user-123';
        $sessionId = 'session-456';
        $companyId = 'company-789';

        // Act
        $context = new UserContext(
            userId: $userId,
            sessionId: $sessionId,
            companyId: $companyId
        );

        // Assert
        self::assertSame($userId, $context->getUserId());
        self::assertSame($sessionId, $context->getSessionId());
        self::assertSame($companyId, $context->getCompanyId());
    }

    #[Test]
    public function it_implements_context_interface(): void
    {
        // Arrange
        $context = new UserContext(userId: 'user-123');

        // Act & Assert
        self::assertInstanceOf(ContextInterface::class, $context);
    }

    #[Test]
    #[DataProvider('provideToArrayScenarios')]
    public function it_converts_to_array_correctly(
        string $userId,
        ?string $sessionId,
        ?string $companyId,
        array $expectedArray
    ): void {
        // Arrange
        $context = new UserContext(
            userId: $userId,
            sessionId: $sessionId,
            companyId: $companyId
        );

        // Act
        $result = $context->toArray();

        // Assert
        self::assertSame($expectedArray, $result);
    }

    public static function provideToArrayScenarios(): iterable
    {
        yield 'only user id' => [
            'userId' => 'user-1',
            'sessionId' => null,
            'companyId' => null,
            'expectedArray' => [
                'user_id' => 'user-1',
            ],
        ];

        yield 'user id and session id' => [
            'userId' => 'user-2',
            'sessionId' => 'session-2',
            'companyId' => null,
            'expectedArray' => [
                'user_id' => 'user-2',
                'session_id' => 'session-2',
            ],
        ];

        yield 'user id and company id' => [
            'userId' => 'user-3',
            'sessionId' => null,
            'companyId' => 'company-3',
            'expectedArray' => [
                'user_id' => 'user-3',
                'company_id' => 'company-3',
            ],
        ];

        yield 'all fields populated' => [
            'userId' => 'user-4',
            'sessionId' => 'session-4',
            'companyId' => 'company-4',
            'expectedArray' => [
                'user_id' => 'user-4',
                'session_id' => 'session-4',
                'company_id' => 'company-4',
            ],
        ];
    }

    #[Test]
    public function it_handles_special_characters_in_user_id(): void
    {
        // Arrange
        $userId = 'user-!@#$%^&*()';

        // Act
        $context = new UserContext(userId: $userId);

        // Assert
        self::assertSame($userId, $context->getUserId());
    }

    #[Test]
    public function it_handles_unicode_characters_in_values(): void
    {
        // Arrange
        $userId = 'пользователь-123';
        $sessionId = 'сессия-456';
        $companyId = '公司-789';

        // Act
        $context = new UserContext(
            userId: $userId,
            sessionId: $sessionId,
            companyId: $companyId
        );

        // Assert
        self::assertSame($userId, $context->getUserId());
        self::assertSame($sessionId, $context->getSessionId());
        self::assertSame($companyId, $context->getCompanyId());
    }

    #[Test]
    public function it_handles_empty_string_as_user_id(): void
    {
        // Arrange
        $userId = '';

        // Act
        $context = new UserContext(userId: $userId);

        // Assert
        self::assertSame('', $context->getUserId());
    }

    #[Test]
    public function it_handles_very_long_user_id(): void
    {
        // Arrange
        $userId = str_repeat('a', 1000);

        // Act
        $context = new UserContext(userId: $userId);

        // Assert
        self::assertSame($userId, $context->getUserId());
        self::assertSame(1000, strlen($context->getUserId()));
    }

    #[Test]
    public function it_preserves_whitespace_in_values(): void
    {
        // Arrange
        $userId = ' user 123 ';
        $sessionId = '  session  ';

        // Act
        $context = new UserContext(
            userId: $userId,
            sessionId: $sessionId
        );

        // Assert
        self::assertSame($userId, $context->getUserId());
        self::assertSame($sessionId, $context->getSessionId());
    }

    #[Test]
    public function it_handles_numeric_string_values(): void
    {
        // Arrange
        $userId = '12345';
        $sessionId = '67890';
        $companyId = '99999';

        // Act
        $context = new UserContext(
            userId: $userId,
            sessionId: $sessionId,
            companyId: $companyId
        );

        // Assert
        self::assertSame($userId, $context->getUserId());
        self::assertSame($sessionId, $context->getSessionId());
        self::assertSame($companyId, $context->getCompanyId());
    }

    #[Test]
    public function it_handles_email_as_user_id(): void
    {
        // Arrange
        $userId = 'user@example.com';

        // Act
        $context = new UserContext(userId: $userId);

        // Assert
        self::assertSame($userId, $context->getUserId());
    }

    #[Test]
    public function it_handles_uuid_as_user_id(): void
    {
        // Arrange
        $userId = '550e8400-e29b-41d4-a716-446655440000';

        // Act
        $context = new UserContext(userId: $userId);

        // Assert
        self::assertSame($userId, $context->getUserId());
    }

    #[Test]
    public function it_returns_consistent_array_on_multiple_calls(): void
    {
        // Arrange
        $context = new UserContext(
            userId: 'user-123',
            sessionId: 'session-456',
            companyId: 'company-789'
        );

        // Act
        $array1 = $context->toArray();
        $array2 = $context->toArray();

        // Assert
        self::assertSame($array1, $array2);
    }
}
