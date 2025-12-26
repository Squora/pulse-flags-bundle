<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Tests\Strategy\Operator;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pulse\Flags\Core\Enum\AttributeOperator;
use Pulse\Flags\Core\Strategy\Operator\ContainsOperator;
use Pulse\Flags\Core\Strategy\Operator\OperatorInterface;

final class ContainsOperatorTest extends TestCase
{
    #[Test]
    public function it_implements_operator_interface(): void
    {
        // Arrange
        $operator = new ContainsOperator();

        // Act & Assert
        self::assertInstanceOf(OperatorInterface::class, $operator);
    }

    #[Test]
    public function it_returns_correct_operator_type(): void
    {
        // Arrange
        $operator = new ContainsOperator();

        // Act
        $result = $operator->getOperator();

        // Assert
        self::assertSame(AttributeOperator::CONTAINS, $result);
    }

    #[Test]
    #[DataProvider('provideContainsValues')]
    public function it_returns_true_when_string_contains_substring(
        string $actual,
        string $expected
    ): void {
        // Arrange
        $operator = new ContainsOperator();

        // Act
        $result = $operator->evaluate($actual, $expected);

        // Assert
        self::assertTrue($result);
    }

    public static function provideContainsValues(): iterable
    {
        yield 'substring in middle' => ['hello world', 'lo wo'];
        yield 'substring at start' => ['hello world', 'hello'];
        yield 'substring at end' => ['hello world', 'world'];
        yield 'single character' => ['test', 't'];
        yield 'entire string' => ['test', 'test'];
        yield 'email domain' => ['john@company.com', '@company.com'];
        yield 'unicode substring' => ['Привет мир', 'вет'];
        yield 'special characters' => ['test!@#$', '!@#'];
        yield 'space in substring' => ['hello world', ' '];
        yield 'empty substring in string' => ['test', ''];
    }

    #[Test]
    #[DataProvider('provideNotContainsValues')]
    public function it_returns_false_when_string_does_not_contain_substring(
        string $actual,
        string $expected
    ): void {
        // Arrange
        $operator = new ContainsOperator();

        // Act
        $result = $operator->evaluate($actual, $expected);

        // Assert
        self::assertFalse($result);
    }

    public static function provideNotContainsValues(): iterable
    {
        yield 'substring not present' => ['hello world', 'xyz'];
        yield 'email different domain' => ['john@gmail.com', '@company.com'];
        yield 'case sensitive' => ['Hello', 'hello'];
        yield 'reversed order' => ['abc', 'cba'];
        yield 'partial match' => ['test', 'testing'];
        yield 'unicode not present' => ['Hello', 'Привет'];
    }

    #[Test]
    #[DataProvider('provideNonStringValues')]
    public function it_returns_false_for_non_string_values(mixed $actual, mixed $expected): void
    {
        // Arrange
        $operator = new ContainsOperator();

        // Act
        $result = $operator->evaluate($actual, $expected);

        // Assert
        self::assertFalse($result);
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
        $operator = new ContainsOperator();

        // Act & Assert
        self::assertTrue($operator->evaluate('Hello World', 'Hello'));
        self::assertFalse($operator->evaluate('Hello World', 'hello'));
        self::assertTrue($operator->evaluate('TEST', 'TEST'));
        self::assertFalse($operator->evaluate('TEST', 'test'));
    }

    #[Test]
    public function it_handles_empty_strings(): void
    {
        // Arrange
        $operator = new ContainsOperator();

        // Act & Assert
        self::assertTrue($operator->evaluate('test', ''));
        self::assertTrue($operator->evaluate('', ''));
        self::assertFalse($operator->evaluate('', 'test'));
    }

    #[Test]
    public function it_handles_unicode_strings(): void
    {
        // Arrange
        $operator = new ContainsOperator();

        // Act & Assert
        self::assertTrue($operator->evaluate('Привет мир', 'Привет'));
        self::assertTrue($operator->evaluate('日本語テスト', '本語'));
        self::assertTrue($operator->evaluate('emoji 😀 test', '😀'));
        self::assertFalse($operator->evaluate('Привет', 'привет')); // case sensitive
    }

    #[Test]
    public function it_handles_special_characters(): void
    {
        // Arrange
        $operator = new ContainsOperator();

        // Act & Assert
        self::assertTrue($operator->evaluate('test!@#$%^&*()', '!@#$'));
        self::assertTrue($operator->evaluate('path/to/file', '/to/'));
        self::assertTrue($operator->evaluate('key=value&other=thing', '=value&'));
    }

    #[Test]
    public function it_handles_whitespace(): void
    {
        // Arrange
        $operator = new ContainsOperator();

        // Act & Assert
        self::assertTrue($operator->evaluate('hello world', ' '));
        self::assertTrue($operator->evaluate("line1\nline2", "\n"));
        self::assertTrue($operator->evaluate("tab\there", "\t"));
        self::assertFalse($operator->evaluate('helloworld', ' '));
    }

    #[Test]
    public function it_handles_repeated_substrings(): void
    {
        // Arrange
        $operator = new ContainsOperator();

        // Act & Assert
        self::assertTrue($operator->evaluate('ababab', 'ab'));
        self::assertTrue($operator->evaluate('testtesttest', 'test'));
        self::assertTrue($operator->evaluate('aaa', 'aa'));
    }

    #[Test]
    public function it_handles_email_example(): void
    {
        // Arrange
        $operator = new ContainsOperator();

        // Act & Assert - from class documentation
        self::assertTrue($operator->evaluate('john@company.com', '@company.com'));
        self::assertFalse($operator->evaluate('john@gmail.com', '@company.com'));
    }
}
