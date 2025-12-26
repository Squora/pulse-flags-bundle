<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Tests\Context;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pulse\Flags\Core\Context\ContextInterface;
use Pulse\Flags\Core\Context\EmptyContext;

final class EmptyContextTest extends TestCase
{
    #[Test]
    public function it_can_be_created_without_parameters(): void
    {
        // Arrange & Act
        $context = new EmptyContext();

        // Assert
        self::assertInstanceOf(EmptyContext::class, $context);
    }

    #[Test]
    public function it_implements_context_interface(): void
    {
        // Arrange
        $context = new EmptyContext();

        // Act & Assert
        self::assertInstanceOf(ContextInterface::class, $context);
    }

    #[Test]
    public function it_returns_empty_array_from_to_array(): void
    {
        // Arrange
        $context = new EmptyContext();

        // Act
        $result = $context->toArray();

        // Assert
        self::assertSame([], $result);
        self::assertIsArray($result);
        self::assertEmpty($result);
    }

    #[Test]
    public function it_returns_consistent_empty_array_on_multiple_calls(): void
    {
        // Arrange
        $context = new EmptyContext();

        // Act
        $array1 = $context->toArray();
        $array2 = $context->toArray();
        $array3 = $context->toArray();

        // Assert
        self::assertSame([], $array1);
        self::assertSame([], $array2);
        self::assertSame([], $array3);
        self::assertSame($array1, $array2);
        self::assertSame($array2, $array3);
    }

    #[Test]
    public function it_can_be_used_in_composite_context(): void
    {
        // Arrange
        $context1 = new EmptyContext();
        $context2 = new EmptyContext();

        // Act
        $merged = array_merge($context1->toArray(), $context2->toArray());

        // Assert
        self::assertSame([], $merged);
    }

    #[Test]
    public function multiple_instances_are_independent(): void
    {
        // Arrange & Act
        $context1 = new EmptyContext();
        $context2 = new EmptyContext();

        // Assert
        self::assertNotSame($context1, $context2);
        self::assertEquals($context1->toArray(), $context2->toArray());
    }

    #[Test]
    public function it_has_no_side_effects(): void
    {
        // Arrange
        $context = new EmptyContext();

        // Act - call multiple times
        $context->toArray();
        $context->toArray();
        $result = $context->toArray();

        // Assert - should still return empty array
        self::assertSame([], $result);
    }
}
