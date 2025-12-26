<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Tests\Context;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pulse\Flags\Core\Context\CompositeContext;
use Pulse\Flags\Core\Context\ContextInterface;
use Pulse\Flags\Core\Context\EmptyContext;
use Pulse\Flags\Core\Context\GeoContext;
use Pulse\Flags\Core\Context\UserContext;

final class CompositeContextTest extends TestCase
{
    #[Test]
    public function it_can_be_created_with_single_context(): void
    {
        // Arrange
        $userContext = new UserContext(userId: 'user-123');

        // Act
        $compositeContext = new CompositeContext($userContext);

        // Assert
        self::assertInstanceOf(CompositeContext::class, $compositeContext);
        self::assertCount(1, $compositeContext->getContexts());
        self::assertSame($userContext, $compositeContext->getContexts()[0]);
    }

    #[Test]
    public function it_can_be_created_with_multiple_contexts(): void
    {
        // Arrange
        $userContext = new UserContext(userId: 'user-123');
        $geoContext = new GeoContext(country: 'US');

        // Act
        $compositeContext = new CompositeContext($userContext, $geoContext);

        // Assert
        self::assertCount(2, $compositeContext->getContexts());
        self::assertSame($userContext, $compositeContext->getContexts()[0]);
        self::assertSame($geoContext, $compositeContext->getContexts()[1]);
    }

    #[Test]
    public function it_can_be_created_with_empty_contexts(): void
    {
        // Arrange & Act
        $compositeContext = new CompositeContext();

        // Assert
        self::assertCount(0, $compositeContext->getContexts());
        self::assertSame([], $compositeContext->getContexts());
    }

    #[Test]
    public function it_returns_all_contexts_via_get_contexts(): void
    {
        // Arrange
        $contexts = [
            new UserContext(userId: 'user-123'),
            new GeoContext(country: 'US', region: 'CA'),
            new EmptyContext(),
        ];

        // Act
        $compositeContext = new CompositeContext(...$contexts);

        // Assert
        $returnedContexts = $compositeContext->getContexts();
        self::assertCount(3, $returnedContexts);
        self::assertSame($contexts[0], $returnedContexts[0]);
        self::assertSame($contexts[1], $returnedContexts[1]);
        self::assertSame($contexts[2], $returnedContexts[2]);
    }

    #[Test]
    public function it_implements_context_interface(): void
    {
        // Arrange
        $compositeContext = new CompositeContext();

        // Act & Assert
        self::assertInstanceOf(ContextInterface::class, $compositeContext);
    }

    #[Test]
    #[DataProvider('provideContextsForArrayMerging')]
    public function it_merges_all_contexts_to_array(
        array $contexts,
        array $expectedArray
    ): void {
        // Arrange
        $compositeContext = new CompositeContext(...$contexts);

        // Act
        $result = $compositeContext->toArray();

        // Assert
        self::assertSame($expectedArray, $result);
    }

    #[Test]
    public function it_returns_empty_array_when_no_contexts_provided(): void
    {
        // Arrange
        $compositeContext = new CompositeContext();

        // Act
        $result = $compositeContext->toArray();

        // Assert
        self::assertSame([], $result);
    }

    #[Test]
    public function it_returns_empty_array_when_all_contexts_are_empty(): void
    {
        // Arrange
        $compositeContext = new CompositeContext(
            new EmptyContext(),
            new EmptyContext()
        );

        // Act
        $result = $compositeContext->toArray();

        // Assert
        self::assertSame([], $result);
    }

    #[Test]
    public function it_merges_contexts_in_correct_order(): void
    {
        // Arrange
        $userContext = new UserContext(
            userId: 'user-123',
            sessionId: 'session-456',
            companyId: 'company-789'
        );
        $geoContext = new GeoContext(
            country: 'US',
            region: 'CA',
            city: 'San Francisco'
        );

        // Act
        $compositeContext = new CompositeContext($userContext, $geoContext);
        $result = $compositeContext->toArray();

        // Assert
        self::assertSame([
            'user_id' => 'user-123',
            'session_id' => 'session-456',
            'company_id' => 'company-789',
            'country' => 'US',
            'region' => 'CA',
            'city' => 'San Francisco',
        ], $result);
    }

    #[Test]
    public function it_handles_overlapping_keys_by_keeping_last_value(): void
    {
        // Arrange
        $context1 = new class implements ContextInterface {
            public function toArray(): array
            {
                return ['key' => 'first', 'unique1' => 'value1'];
            }
        };

        $context2 = new class implements ContextInterface {
            public function toArray(): array
            {
                return ['key' => 'second', 'unique2' => 'value2'];
            }
        };

        // Act
        $compositeContext = new CompositeContext($context1, $context2);
        $result = $compositeContext->toArray();

        // Assert
        self::assertSame([
            'key' => 'second',
            'unique1' => 'value1',
            'unique2' => 'value2',
        ], $result);
    }

    #[Test]
    public function it_preserves_array_values_in_merged_result(): void
    {
        // Arrange
        $context = new class implements ContextInterface {
            public function toArray(): array
            {
                return [
                    'string' => 'value',
                    'int' => 42,
                    'float' => 3.14,
                    'bool' => true,
                    'null' => null,
                    'array' => [1, 2, 3],
                ];
            }
        };

        // Act
        $compositeContext = new CompositeContext($context);
        $result = $compositeContext->toArray();

        // Assert
        self::assertSame([
            'string' => 'value',
            'int' => 42,
            'float' => 3.14,
            'bool' => true,
            'null' => null,
            'array' => [1, 2, 3],
        ], $result);
    }

    public static function provideContextsForArrayMerging(): iterable
    {
        yield 'single user context with minimal data' => [
            'contexts' => [
                new UserContext(userId: 'user-123'),
            ],
            'expectedArray' => [
                'user_id' => 'user-123',
            ],
        ];

        yield 'single user context with full data' => [
            'contexts' => [
                new UserContext(
                    userId: 'user-123',
                    sessionId: 'session-456',
                    companyId: 'company-789'
                ),
            ],
            'expectedArray' => [
                'user_id' => 'user-123',
                'session_id' => 'session-456',
                'company_id' => 'company-789',
            ],
        ];

        yield 'single geo context with country only' => [
            'contexts' => [
                new GeoContext(country: 'US'),
            ],
            'expectedArray' => [
                'country' => 'US',
            ],
        ];

        yield 'single geo context with full data' => [
            'contexts' => [
                new GeoContext(
                    country: 'US',
                    region: 'CA',
                    city: 'Los Angeles'
                ),
            ],
            'expectedArray' => [
                'country' => 'US',
                'region' => 'CA',
                'city' => 'Los Angeles',
            ],
        ];

        yield 'single empty context' => [
            'contexts' => [
                new EmptyContext(),
            ],
            'expectedArray' => [],
        ];

        yield 'user and geo contexts combined' => [
            'contexts' => [
                new UserContext(userId: 'user-123', sessionId: 'session-456'),
                new GeoContext(country: 'DE', city: 'Berlin'),
            ],
            'expectedArray' => [
                'user_id' => 'user-123',
                'session_id' => 'session-456',
                'country' => 'DE',
                'city' => 'Berlin',
            ],
        ];

        yield 'multiple contexts with empty context' => [
            'contexts' => [
                new UserContext(userId: 'user-123'),
                new EmptyContext(),
                new GeoContext(country: 'FR'),
            ],
            'expectedArray' => [
                'user_id' => 'user-123',
                'country' => 'FR',
            ],
        ];

        yield 'three different contexts' => [
            'contexts' => [
                new UserContext(userId: 'user-999', companyId: 'company-111'),
                new GeoContext(country: 'GB', region: 'England', city: 'London'),
                new EmptyContext(),
            ],
            'expectedArray' => [
                'user_id' => 'user-999',
                'company_id' => 'company-111',
                'country' => 'GB',
                'region' => 'England',
                'city' => 'London',
            ],
        ];
    }
}
