<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Tests\Strategy\Operator;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pulse\Flags\Core\Enum\AttributeOperator;
use Pulse\Flags\Core\Strategy\Operator\InOperator;
use Pulse\Flags\Core\Strategy\Operator\OperatorInterface;

final class InOperatorTest extends TestCase
{
    #[Test]
    public function it_implements_operator_interface(): void
    {
        // Arrange
        $operator = new InOperator();

        // Act & Assert
        self::assertInstanceOf(OperatorInterface::class, $operator);
    }

    #[Test]
    public function it_returns_correct_operator_type(): void
    {
        // Arrange
        $operator = new InOperator();

        // Act
        $result = $operator->getOperator();

        // Assert
        self::assertSame(AttributeOperator::IN, $result);
    }

    #[Test]
    #[DataProvider('provideValueInArray')]
    public function it_returns_true_when_value_exists_in_array(mixed $actual, array $expected): void
    {
        // Arrange
        $operator = new InOperator();

        // Act
        $result = $operator->evaluate($actual, $expected);

        // Assert
        self::assertTrue($result);
    }

    public static function provideValueInArray(): iterable
    {
        yield 'string in array' => ['US', ['US', 'CA', 'GB']];
        yield 'integer in array' => [42, [10, 42, 100]];
        yield 'float in array' => [3.14, [1.5, 3.14, 9.99]];
        yield 'boolean in array' => [true, [true, false]];
        yield 'null in array' => [null, [null, 'test']];
        yield 'first element' => ['a', ['a', 'b', 'c']];
        yield 'last element' => ['c', ['a', 'b', 'c']];
        yield 'single element array' => ['test', ['test']];
        yield 'unicode string' => ['Россия', ['США', 'Россия', 'Япония']];
    }

    #[Test]
    #[DataProvider('provideValueNotInArray')]
    public function it_returns_false_when_value_does_not_exist_in_array(
        mixed $actual,
        array $expected
    ): void {
        // Arrange
        $operator = new InOperator();

        // Act
        $result = $operator->evaluate($actual, $expected);

        // Assert
        self::assertFalse($result);
    }

    public static function provideValueNotInArray(): iterable
    {
        yield 'string not in array' => ['FR', ['US', 'CA', 'GB']];
        yield 'integer not in array' => [50, [10, 42, 100]];
        yield 'empty array' => ['test', []];
        yield 'different type' => [42, ['42']];
        yield 'float vs integer' => [1.0, [1]];
        yield 'null vs empty string' => [null, ['']];
        yield 'boolean vs integer' => [true, [1]];
    }

    #[Test]
    public function it_uses_strict_comparison(): void
    {
        // Arrange
        $operator = new InOperator();

        // Act & Assert - uses strict comparison (in_array third param = true)
        self::assertFalse($operator->evaluate(1, ['1', '2', '3']));
        self::assertFalse($operator->evaluate('1', [1, 2, 3]));
        self::assertFalse($operator->evaluate(true, [1, '1']));
        self::assertFalse($operator->evaluate(false, [0, '0']));
        self::assertFalse($operator->evaluate(null, [0, '', false]));
    }

    #[Test]
    public function it_returns_false_when_expected_is_not_array(): void
    {
        // Arrange
        $operator = new InOperator();

        // Act & Assert
        self::assertFalse($operator->evaluate('US', 'US'));
        self::assertFalse($operator->evaluate(42, 42));
        self::assertFalse($operator->evaluate('test', null));
        self::assertFalse($operator->evaluate('test', 'not an array'));
    }

    #[Test]
    public function it_handles_nested_arrays(): void
    {
        // Arrange
        $operator = new InOperator();
        $nestedArray = ['a', 'b'];

        // Act & Assert
        self::assertTrue($operator->evaluate($nestedArray, [['a', 'b'], ['c', 'd']]));
        self::assertFalse($operator->evaluate($nestedArray, [['a'], ['b']]));
    }

    #[Test]
    public function it_handles_unicode_values(): void
    {
        // Arrange
        $operator = new InOperator();

        // Act & Assert
        self::assertTrue($operator->evaluate('Привет', ['Hello', 'Привет', 'こんにちは']));
        self::assertFalse($operator->evaluate('Привет', ['привет'])); // case sensitive
        self::assertTrue($operator->evaluate('日本', ['中国', '日本', '韓国']));
    }

    #[Test]
    public function it_handles_special_characters(): void
    {
        // Arrange
        $operator = new InOperator();

        // Act & Assert
        self::assertTrue($operator->evaluate('!@#$', ['test', '!@#$', 'abc']));
        self::assertFalse($operator->evaluate('!@#$', ['!@#', '$%^']));
    }

    #[Test]
    public function it_handles_empty_string(): void
    {
        // Arrange
        $operator = new InOperator();

        // Act & Assert
        self::assertTrue($operator->evaluate('', ['', 'test']));
        self::assertFalse($operator->evaluate('', ['test', 'other']));
    }

    #[Test]
    public function it_handles_large_arrays(): void
    {
        // Arrange
        $operator = new InOperator();
        $largeArray = range(1, 1000);

        // Act & Assert
        self::assertTrue($operator->evaluate(500, $largeArray));
        self::assertFalse($operator->evaluate(1001, $largeArray));
    }

    #[Test]
    public function it_handles_country_code_example(): void
    {
        // Arrange
        $operator = new InOperator();

        // Act & Assert - from class documentation
        self::assertTrue($operator->evaluate('US', ['US', 'CA', 'GB']));
        self::assertFalse($operator->evaluate('FR', ['US', 'CA', 'GB']));
    }
}
