<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Tests\Strategy\Operator;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pulse\Flags\Core\Enum\AttributeOperator;
use Pulse\Flags\Core\Strategy\Operator\OperatorInterface;
use Pulse\Flags\Core\Strategy\Operator\StartsWithOperator;

final class StartsWithOperatorTest extends TestCase
{
    #[Test]
    public function it_implements_operator_interface(): void
    {
        // Arrange
        $operator = new StartsWithOperator();

        // Act & Assert
        self::assertInstanceOf(OperatorInterface::class, $operator);
    }

    #[Test]
    public function it_returns_correct_operator_type(): void
    {
        // Arrange
        $operator = new StartsWithOperator();

        // Act
        $result = $operator->getOperator();

        // Assert
        self::assertSame(AttributeOperator::STARTS_WITH, $result);
    }

    #[Test]
    #[DataProvider('provideStartsWithValues')]
    public function it_returns_true_when_string_starts_with_prefix(
        string $actual,
        string $expected
    ): void {
        // Arrange
        $operator = new StartsWithOperator();

        // Act
        $result = $operator->evaluate($actual, $expected);

        // Assert
        self::assertTrue($result);
    }

    public static function provideStartsWithValues(): iterable
    {
        yield 'simple prefix' => ['hello world', 'hello'];
        yield 'single character' => ['test', 't'];
        yield 'entire string' => ['test', 'test'];
        yield 'user agent Mozilla' => ['Mozilla/5.0 ...', 'Mozilla'];
        yield 'http protocol' => ['https://example.com', 'https://'];
        yield 'path prefix' => ['/var/www/html', '/var'];
        yield 'unicode prefix' => ['Привет мир', 'Привет'];
        yield 'special characters' => ['!@#$test', '!@#$'];
        yield 'empty prefix' => ['test', ''];
        yield 'whitespace prefix' => [' test', ' '];
    }

    #[Test]
    #[DataProvider('provideNotStartsWithValues')]
    public function it_returns_false_when_string_does_not_start_with_prefix(
        string $actual,
        string $expected
    ): void {
        // Arrange
        $operator = new StartsWithOperator();

        // Act
        $result = $operator->evaluate($actual, $expected);

        // Assert
        self::assertFalse($result);
    }

    public static function provideNotStartsWithValues(): iterable
    {
        yield 'prefix in middle' => ['hello world', 'world'];
        yield 'prefix at end' => ['hello world', 'ld'];
        yield 'case sensitive' => ['Hello', 'hello'];
        yield 'longer than string' => ['test', 'testing'];
        yield 'different prefix' => ['Mozilla/5.0', 'Chrome'];
        yield 'unicode different' => ['Hello', 'Привет'];
        yield 'space prefix mismatch' => ['test', ' '];
    }

    #[Test]
    #[DataProvider('provideNonStringValues')]
    public function it_returns_false_for_non_string_values(mixed $actual, mixed $expected): void
    {
        // Arrange
        $operator = new StartsWithOperator();

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
        $operator = new StartsWithOperator();

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
        $operator = new StartsWithOperator();

        // Act & Assert
        self::assertTrue($operator->evaluate('test', ''));
        self::assertTrue($operator->evaluate('', ''));
        self::assertFalse($operator->evaluate('', 'test'));
    }

    #[Test]
    public function it_handles_unicode_strings(): void
    {
        // Arrange
        $operator = new StartsWithOperator();

        // Act & Assert
        self::assertTrue($operator->evaluate('Привет мир', 'Привет'));
        self::assertTrue($operator->evaluate('日本語テスト', '日本'));
        self::assertTrue($operator->evaluate('😀 emoji test', '😀'));
        self::assertFalse($operator->evaluate('Привет', 'привет')); // case sensitive
    }

    #[Test]
    public function it_handles_special_characters(): void
    {
        // Arrange
        $operator = new StartsWithOperator();

        // Act & Assert
        self::assertTrue($operator->evaluate('!@#$%^&*()', '!@#$'));
        self::assertTrue($operator->evaluate('/path/to/file', '/path'));
        self::assertTrue($operator->evaluate('https://example.com', 'https://'));
        self::assertFalse($operator->evaluate('test!@#$', '!@#$'));
    }

    #[Test]
    public function it_handles_whitespace(): void
    {
        // Arrange
        $operator = new StartsWithOperator();

        // Act & Assert
        self::assertTrue($operator->evaluate(' hello', ' '));
        self::assertTrue($operator->evaluate("\nhello", "\n"));
        self::assertTrue($operator->evaluate("\thello", "\t"));
        self::assertFalse($operator->evaluate('hello', ' '));
    }

    #[Test]
    public function it_handles_repeated_prefixes(): void
    {
        // Arrange
        $operator = new StartsWithOperator();

        // Act & Assert
        self::assertTrue($operator->evaluate('ababab', 'ab'));
        self::assertTrue($operator->evaluate('testtesttest', 'test'));
        self::assertTrue($operator->evaluate('aaa', 'a'));
        self::assertTrue($operator->evaluate('aaa', 'aa'));
    }

    #[Test]
    public function it_handles_user_agent_example(): void
    {
        // Arrange
        $operator = new StartsWithOperator();

        // Act & Assert - from class documentation
        self::assertTrue($operator->evaluate('Mozilla/5.0 ...', 'Mozilla'));
        self::assertFalse($operator->evaluate('Chrome/91.0 ...', 'Mozilla'));
    }

    #[Test]
    public function it_handles_url_protocols(): void
    {
        // Arrange
        $operator = new StartsWithOperator();

        // Act & Assert
        self::assertTrue($operator->evaluate('https://example.com', 'https'));
        self::assertTrue($operator->evaluate('http://example.com', 'http'));
        self::assertTrue($operator->evaluate('ftp://server.com', 'ftp'));
        self::assertFalse($operator->evaluate('https://example.com', 'http://'));
    }
}
