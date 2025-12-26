<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Tests\Strategy\Operator;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pulse\Flags\Core\Enum\AttributeOperator;
use Pulse\Flags\Core\Strategy\Operator\NotContainsOperator;
use Pulse\Flags\Core\Strategy\Operator\OperatorInterface;

final class NotContainsOperatorTest extends TestCase
{
    #[Test]
    public function it_implements_operator_interface(): void
    {
        // Arrange
        $operator = new NotContainsOperator();

        // Act & Assert
        self::assertInstanceOf(OperatorInterface::class, $operator);
    }

    #[Test]
    public function it_returns_correct_operator_type(): void
    {
        // Arrange
        $operator = new NotContainsOperator();

        // Act
        $result = $operator->getOperator();

        // Assert
        self::assertSame(AttributeOperator::NOT_CONTAINS, $result);
    }

    #[Test]
    #[DataProvider('provideNotContainsValues')]
    public function it_returns_true_when_string_does_not_contain_substring(
        string $actual,
        string $expected
    ): void {
        // Arrange
        $operator = new NotContainsOperator();

        // Act
        $result = $operator->evaluate($actual, $expected);

        // Assert
        self::assertTrue($result);
    }

    public static function provideNotContainsValues(): iterable
    {
        yield 'substring not present' => ['hello world', 'xyz'];
        yield 'email different domain' => ['john@company.com', '@competitor.com'];
        yield 'case sensitive' => ['Hello', 'hello'];
        yield 'reversed order' => ['abc', 'cba'];
        yield 'partial match' => ['test', 'testing'];
        yield 'unicode not present' => ['Hello', 'Привет'];
        yield 'different special chars' => ['test!@#', '$%^'];
    }

    #[Test]
    #[DataProvider('provideContainsValues')]
    public function it_returns_false_when_string_contains_substring(
        string $actual,
        string $expected
    ): void {
        // Arrange
        $operator = new NotContainsOperator();

        // Act
        $result = $operator->evaluate($actual, $expected);

        // Assert
        self::assertFalse($result);
    }

    public static function provideContainsValues(): iterable
    {
        yield 'substring in middle' => ['hello world', 'lo wo'];
        yield 'substring at start' => ['hello world', 'hello'];
        yield 'substring at end' => ['hello world', 'world'];
        yield 'single character' => ['test', 't'];
        yield 'entire string' => ['test', 'test'];
        yield 'email competitor domain' => ['john@competitor.com', '@competitor.com'];
        yield 'unicode substring' => ['Привет мир', 'вет'];
        yield 'special characters' => ['test!@#$', '!@#'];
        yield 'empty substring' => ['test', ''];
    }

    #[Test]
    #[DataProvider('provideNonStringValues')]
    public function it_returns_true_for_non_string_values(mixed $actual, mixed $expected): void
    {
        // Arrange
        $operator = new NotContainsOperator();

        // Act
        $result = $operator->evaluate($actual, $expected);

        // Assert
        self::assertTrue($result);
    }

    public static function provideNonStringValues(): iterable
    {
        yield 'integer actual' => [123, 'test'];
        yield 'integer expected' => ['test', 123];
        yield 'null actual' => [null, 'test'];
        yield 'null expected' => ['test', null];
        yield 'boolean actual' => [true, 'test'];
        yield 'boolean expected' => ['test', false];
        yield 'array actual' => [['test'], 'test'];
        yield 'array expected' => ['test', ['test']];
        yield 'both integers' => [123, 12];
        yield 'both null' => [null, null];
    }

    #[Test]
    public function it_is_case_sensitive(): void
    {
        // Arrange
        $operator = new NotContainsOperator();

        // Act & Assert
        self::assertFalse($operator->evaluate('Hello World', 'Hello'));
        self::assertTrue($operator->evaluate('Hello World', 'hello'));
        self::assertFalse($operator->evaluate('TEST', 'TEST'));
        self::assertTrue($operator->evaluate('TEST', 'test'));
    }

    #[Test]
    public function it_handles_empty_strings(): void
    {
        // Arrange
        $operator = new NotContainsOperator();

        // Act & Assert
        self::assertFalse($operator->evaluate('test', ''));
        self::assertFalse($operator->evaluate('', ''));
        self::assertTrue($operator->evaluate('', 'test'));
    }

    #[Test]
    public function it_handles_unicode_strings(): void
    {
        // Arrange
        $operator = new NotContainsOperator();

        // Act & Assert
        self::assertFalse($operator->evaluate('Привет мир', 'Привет'));
        self::assertFalse($operator->evaluate('日本語テスト', '本語'));
        self::assertFalse($operator->evaluate('emoji 😀 test', '😀'));
        self::assertTrue($operator->evaluate('Привет', 'привет')); // case sensitive
    }

    #[Test]
    public function it_handles_special_characters(): void
    {
        // Arrange
        $operator = new NotContainsOperator();

        // Act & Assert
        self::assertFalse($operator->evaluate('test!@#$%^&*()', '!@#$'));
        self::assertTrue($operator->evaluate('test!@#$%^&*()', 'xyz'));
        self::assertFalse($operator->evaluate('path/to/file', '/to/'));
    }

    #[Test]
    public function it_handles_whitespace(): void
    {
        // Arrange
        $operator = new NotContainsOperator();

        // Act & Assert
        self::assertFalse($operator->evaluate('hello world', ' '));
        self::assertTrue($operator->evaluate('helloworld', ' '));
        self::assertFalse($operator->evaluate("line1\nline2", "\n"));
        self::assertTrue($operator->evaluate('single line', "\n"));
    }

    #[Test]
    public function it_handles_email_example(): void
    {
        // Arrange
        $operator = new NotContainsOperator();

        // Act & Assert - from class documentation
        self::assertTrue($operator->evaluate('john@company.com', '@competitor.com'));
        self::assertFalse($operator->evaluate('john@competitor.com', '@competitor.com'));
    }
}
