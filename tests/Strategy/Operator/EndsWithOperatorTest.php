<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Tests\Strategy\Operator;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pulse\Flags\Core\Enum\AttributeOperator;
use Pulse\Flags\Core\Strategy\Operator\EndsWithOperator;
use Pulse\Flags\Core\Strategy\Operator\OperatorInterface;

final class EndsWithOperatorTest extends TestCase
{
    #[Test]
    public function it_implements_operator_interface(): void
    {
        // Arrange
        $operator = new EndsWithOperator();

        // Act & Assert
        self::assertInstanceOf(OperatorInterface::class, $operator);
    }

    #[Test]
    public function it_returns_correct_operator_type(): void
    {
        // Arrange
        $operator = new EndsWithOperator();

        // Act
        $result = $operator->getOperator();

        // Assert
        self::assertSame(AttributeOperator::ENDS_WITH, $result);
    }

    #[Test]
    #[DataProvider('provideEndsWithValues')]
    public function it_returns_true_when_string_ends_with_suffix(
        string $actual,
        string $expected
    ): void {
        // Arrange
        $operator = new EndsWithOperator();

        // Act
        $result = $operator->evaluate($actual, $expected);

        // Assert
        self::assertTrue($result);
    }

    public static function provideEndsWithValues(): iterable
    {
        yield 'simple suffix' => ['hello world', 'world'];
        yield 'single character' => ['test', 't'];
        yield 'entire string' => ['test', 'test'];
        yield 'email .edu' => ['student@university.edu', '.edu'];
        yield 'file extension' => ['document.pdf', '.pdf'];
        yield 'path suffix' => ['/var/www/html', 'html'];
        yield 'unicode suffix' => ['Привет мир', 'мир'];
        yield 'special characters' => ['test!@#$', '!@#$'];
        yield 'empty suffix' => ['test', ''];
        yield 'whitespace suffix' => ['test ', ' '];
    }

    #[Test]
    #[DataProvider('provideNotEndsWithValues')]
    public function it_returns_false_when_string_does_not_end_with_suffix(
        string $actual,
        string $expected
    ): void {
        // Arrange
        $operator = new EndsWithOperator();

        // Act
        $result = $operator->evaluate($actual, $expected);

        // Assert
        self::assertFalse($result);
    }

    public static function provideNotEndsWithValues(): iterable
    {
        yield 'suffix in middle' => ['hello world', 'wor'];
        yield 'suffix at start' => ['hello world', 'hello'];
        yield 'case sensitive' => ['Hello', 'HELLO'];
        yield 'longer than string' => ['test', 'testing'];
        yield 'different suffix' => ['file.pdf', '.doc'];
        yield 'email wrong domain' => ['student@gmail.com', '.edu'];
        yield 'unicode different' => ['Hello', 'Привет'];
        yield 'space suffix mismatch' => ['test', ' '];
    }

    #[Test]
    #[DataProvider('provideNonStringValues')]
    public function it_returns_false_for_non_string_values(mixed $actual, mixed $expected): void
    {
        // Arrange
        $operator = new EndsWithOperator();

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
        yield 'both integers' => [123, 23];
        yield 'both null' => [null, null];
    }

    #[Test]
    public function it_is_case_sensitive(): void
    {
        // Arrange
        $operator = new EndsWithOperator();

        // Act & Assert
        self::assertTrue($operator->evaluate('Hello World', 'World'));
        self::assertFalse($operator->evaluate('Hello World', 'world'));
        self::assertTrue($operator->evaluate('TEST', 'TEST'));
        self::assertFalse($operator->evaluate('TEST', 'test'));
    }

    #[Test]
    public function it_handles_empty_strings(): void
    {
        // Arrange
        $operator = new EndsWithOperator();

        // Act & Assert
        self::assertTrue($operator->evaluate('test', ''));
        self::assertTrue($operator->evaluate('', ''));
        self::assertFalse($operator->evaluate('', 'test'));
    }

    #[Test]
    public function it_handles_unicode_strings(): void
    {
        // Arrange
        $operator = new EndsWithOperator();

        // Act & Assert
        self::assertTrue($operator->evaluate('Привет мир', 'мир'));
        self::assertTrue($operator->evaluate('日本語テスト', 'テスト'));
        self::assertTrue($operator->evaluate('emoji test 😀', '😀'));
        self::assertFalse($operator->evaluate('Привет', 'ПРИВЕТ')); // case sensitive
    }

    #[Test]
    public function it_handles_special_characters(): void
    {
        // Arrange
        $operator = new EndsWithOperator();

        // Act & Assert
        self::assertTrue($operator->evaluate('test!@#$%^&*()', '&*()'));
        self::assertTrue($operator->evaluate('/path/to/file.txt', '.txt'));
        self::assertTrue($operator->evaluate('query=value&other=thing', '=thing'));
        self::assertFalse($operator->evaluate('test!@#$', 'test'));
    }

    #[Test]
    public function it_handles_whitespace(): void
    {
        // Arrange
        $operator = new EndsWithOperator();

        // Act & Assert
        self::assertTrue($operator->evaluate('hello ', ' '));
        self::assertTrue($operator->evaluate("hello\n", "\n"));
        self::assertTrue($operator->evaluate("hello\t", "\t"));
        self::assertFalse($operator->evaluate('hello', ' '));
    }

    #[Test]
    public function it_handles_repeated_suffixes(): void
    {
        // Arrange
        $operator = new EndsWithOperator();

        // Act & Assert
        self::assertTrue($operator->evaluate('ababab', 'ab'));
        self::assertTrue($operator->evaluate('testtesttest', 'test'));
        self::assertTrue($operator->evaluate('aaa', 'a'));
        self::assertTrue($operator->evaluate('aaa', 'aa'));
    }

    #[Test]
    public function it_handles_email_example(): void
    {
        // Arrange
        $operator = new EndsWithOperator();

        // Act & Assert - from class documentation
        self::assertTrue($operator->evaluate('student@university.edu', '.edu'));
        self::assertFalse($operator->evaluate('student@gmail.com', '.edu'));
    }

    #[Test]
    public function it_handles_file_extensions(): void
    {
        // Arrange
        $operator = new EndsWithOperator();

        // Act & Assert
        self::assertTrue($operator->evaluate('document.pdf', '.pdf'));
        self::assertTrue($operator->evaluate('image.jpg', '.jpg'));
        self::assertTrue($operator->evaluate('script.js', '.js'));
        self::assertFalse($operator->evaluate('document.pdf', '.doc'));
        self::assertFalse($operator->evaluate('README', '.md'));
    }

    #[Test]
    public function it_handles_domain_extensions(): void
    {
        // Arrange
        $operator = new EndsWithOperator();

        // Act & Assert
        self::assertTrue($operator->evaluate('example.com', '.com'));
        self::assertTrue($operator->evaluate('site.org', '.org'));
        self::assertTrue($operator->evaluate('university.edu', '.edu'));
        self::assertFalse($operator->evaluate('example.com', '.org'));
    }
}
