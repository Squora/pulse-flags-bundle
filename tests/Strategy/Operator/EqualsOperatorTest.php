<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Tests\Strategy\Operator;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pulse\Flags\Core\Enum\AttributeOperator;
use Pulse\Flags\Core\Strategy\Operator\EqualsOperator;
use Pulse\Flags\Core\Strategy\Operator\OperatorInterface;

final class EqualsOperatorTest extends TestCase
{
    #[Test]
    public function it_implements_operator_interface(): void
    {
        // Arrange
        $operator = new EqualsOperator();

        // Act & Assert
        self::assertInstanceOf(OperatorInterface::class, $operator);
    }

    #[Test]
    public function it_returns_correct_operator_type(): void
    {
        // Arrange
        $operator = new EqualsOperator();

        // Act
        $result = $operator->getOperator();

        // Assert
        self::assertSame(AttributeOperator::EQUALS, $result);
    }

    #[Test]
    #[DataProvider('provideEqualValues')]
    public function it_returns_true_for_equal_values(mixed $actual, mixed $expected): void
    {
        // Arrange
        $operator = new EqualsOperator();

        // Act
        $result = $operator->evaluate($actual, $expected);

        // Assert
        self::assertTrue($result);
    }

    public static function provideEqualValues(): iterable
    {
        yield 'equal strings' => ['test', 'test'];
        yield 'equal integers' => [42, 42];
        yield 'equal floats' => [3.14, 3.14];
        yield 'both true' => [true, true];
        yield 'both false' => [false, false];
        yield 'both null' => [null, null];
        yield 'empty strings' => ['', ''];
        yield 'zero integers' => [0, 0];
        yield 'zero floats' => [0.0, 0.0];
        yield 'unicode strings' => ['тест', 'тест'];
    }

    #[Test]
    #[DataProvider('provideNonEqualValues')]
    public function it_returns_false_for_non_equal_values(mixed $actual, mixed $expected): void
    {
        // Arrange
        $operator = new EqualsOperator();

        // Act
        $result = $operator->evaluate($actual, $expected);

        // Assert
        self::assertFalse($result);
    }

    public static function provideNonEqualValues(): iterable
    {
        yield 'different strings' => ['test', 'other'];
        yield 'different integers' => [42, 43];
        yield 'different floats' => [3.14, 3.15];
        yield 'true vs false' => [true, false];
        yield 'null vs empty string' => [null, ''];
        yield 'zero vs false' => [0, false];
        yield 'integer vs string' => [42, '42'];
        yield 'float vs integer' => [1.0, 1];
        yield 'empty array vs null' => [[], null];
    }

    #[Test]
    public function it_uses_strict_comparison(): void
    {
        // Arrange
        $operator = new EqualsOperator();

        // Act & Assert - strict comparison (===)
        self::assertFalse($operator->evaluate(1, '1'));
        self::assertFalse($operator->evaluate(1, true));
        self::assertFalse($operator->evaluate(0, false));
        self::assertFalse($operator->evaluate(0, ''));
        self::assertFalse($operator->evaluate(null, false));
    }
}
