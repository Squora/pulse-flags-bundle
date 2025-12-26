<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Tests\Strategy\Operator;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pulse\Flags\Core\Enum\AttributeOperator;
use Pulse\Flags\Core\Strategy\Operator\GreaterThanOrEqualsOperator;
use Pulse\Flags\Core\Strategy\Operator\OperatorInterface;

final class GreaterThanOrEqualsOperatorTest extends TestCase
{
    #[Test]
    public function it_implements_operator_interface(): void
    {
        // Arrange
        $operator = new GreaterThanOrEqualsOperator();

        // Act & Assert
        self::assertInstanceOf(OperatorInterface::class, $operator);
    }

    #[Test]
    public function it_returns_correct_operator_type(): void
    {
        // Arrange
        $operator = new GreaterThanOrEqualsOperator();

        // Act
        $result = $operator->getOperator();

        // Assert
        self::assertSame(AttributeOperator::GREATER_THAN_OR_EQUALS, $result);
    }

    #[Test]
    #[DataProvider('provideGreaterThanOrEqualValues')]
    public function it_returns_true_when_actual_is_greater_than_or_equal_to_expected(
        int|float $actual,
        int|float $expected
    ): void {
        // Arrange
        $operator = new GreaterThanOrEqualsOperator();

        // Act
        $result = $operator->evaluate($actual, $expected);

        // Assert
        self::assertTrue($result);
    }

    public static function provideGreaterThanOrEqualValues(): iterable
    {
        yield 'equal integers' => [10, 10];
        yield 'equal floats' => [3.14, 3.14];
        yield 'greater integer' => [10, 5];
        yield 'greater float' => [10.5, 5.5];
        yield 'equal zero' => [0, 0];
        yield 'negative equal' => [-5, -5];
        yield 'negative greater' => [-5, -10];
        yield 'positive vs negative' => [1, -1];
        yield 'zero vs negative' => [0, -1];
        yield 'small difference' => [1.00001, 1.0];
    }

    #[Test]
    #[DataProvider('provideLessThanValues')]
    public function it_returns_false_when_actual_is_less_than_expected(
        int|float $actual,
        int|float $expected
    ): void {
        // Arrange
        $operator = new GreaterThanOrEqualsOperator();

        // Act
        $result = $operator->evaluate($actual, $expected);

        // Assert
        self::assertFalse($result);
    }

    public static function provideLessThanValues(): iterable
    {
        yield 'integer less than' => [5, 10];
        yield 'float less than' => [5.5, 10.5];
        yield 'negative less' => [-10, -5];
        yield 'negative vs positive' => [-1, 1];
        yield 'negative vs zero' => [-1, 0];
        yield 'small difference' => [1.0, 1.00001];
    }

    #[Test]
    #[DataProvider('provideNonNumericValues')]
    public function it_returns_false_for_non_numeric_values(mixed $actual, mixed $expected): void
    {
        // Arrange
        $operator = new GreaterThanOrEqualsOperator();

        // Act
        $result = $operator->evaluate($actual, $expected);

        // Assert
        self::assertFalse($result);
    }

    public static function provideNonNumericValues(): iterable
    {
        yield 'null values' => [null, null];
        yield 'boolean values' => [true, false];
        yield 'array values' => [[10], [5]];
        yield 'actual is null' => [null, 10];
        yield 'expected is null' => [10, null];
        yield 'actual is non-numeric string' => ['abc', 10];
        yield 'expected is non-numeric string' => [10, 'abc'];
        yield 'both non-numeric strings' => ['abc', 'def'];
        yield 'actual is empty string' => ['', 10];
        yield 'expected is empty string' => [10, ''];
    }

    #[Test]
    public function it_handles_boundary_values(): void
    {
        // Arrange
        $operator = new GreaterThanOrEqualsOperator();

        // Act & Assert
        self::assertTrue($operator->evaluate(99.99, 99.99));
        self::assertTrue($operator->evaluate(149.99, 99.99));
        self::assertFalse($operator->evaluate(49.99, 99.99));
    }

    #[Test]
    public function it_handles_mixed_integer_and_float(): void
    {
        // Arrange
        $operator = new GreaterThanOrEqualsOperator();

        // Act & Assert
        self::assertTrue($operator->evaluate(10.1, 10));
        self::assertTrue($operator->evaluate(10, 9.9));
        self::assertTrue($operator->evaluate(10, 10.0));
        self::assertTrue($operator->evaluate(10.0, 10));
        self::assertFalse($operator->evaluate(9.9, 10));
    }

    #[Test]
    public function it_handles_very_large_numbers(): void
    {
        // Arrange
        $operator = new GreaterThanOrEqualsOperator();

        // Act & Assert
        self::assertTrue($operator->evaluate(PHP_INT_MAX, PHP_INT_MAX));
        self::assertTrue($operator->evaluate(PHP_INT_MAX, PHP_INT_MAX - 1));
        self::assertFalse($operator->evaluate(PHP_INT_MAX - 1, PHP_INT_MAX));
    }

    #[Test]
    public function it_handles_zero_comparison(): void
    {
        // Arrange
        $operator = new GreaterThanOrEqualsOperator();

        // Act & Assert
        self::assertTrue($operator->evaluate(0, 0));
        self::assertTrue($operator->evaluate(1, 0));
        self::assertTrue($operator->evaluate(0.1, 0));
        self::assertFalse($operator->evaluate(0, 1));
        self::assertFalse($operator->evaluate(-1, 0));
    }
}
