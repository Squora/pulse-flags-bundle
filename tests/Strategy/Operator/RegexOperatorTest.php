<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Tests\Strategy\Operator;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pulse\Flags\Core\Enum\AttributeOperator;
use Pulse\Flags\Core\Strategy\Operator\OperatorInterface;
use Pulse\Flags\Core\Strategy\Operator\RegexOperator;

final class RegexOperatorTest extends TestCase
{
    #[Test]
    public function it_implements_operator_interface(): void
    {
        // Arrange
        $operator = new RegexOperator();

        // Act & Assert
        self::assertInstanceOf(OperatorInterface::class, $operator);
    }

    #[Test]
    public function it_returns_correct_operator_type(): void
    {
        // Arrange
        $operator = new RegexOperator();

        // Act
        $result = $operator->getOperator();

        // Assert
        self::assertSame(AttributeOperator::REGEX, $result);
    }

    #[Test]
    #[DataProvider('provideMatchingPatterns')]
    public function it_returns_true_when_pattern_matches(string $actual, string $pattern): void
    {
        // Arrange
        $operator = new RegexOperator();

        // Act
        $result = $operator->evaluate($actual, $pattern);

        // Assert
        self::assertTrue($result);
    }

    public static function provideMatchingPatterns(): iterable
    {
        yield 'phone number prefix' => ['+1-555-1234', '/^\+1/'];
        yield 'email pattern' => ['user@example.com', '/^[a-z]+@[a-z]+\.[a-z]+$/'];
        yield 'digits only' => ['12345', '/^\d+$/'];
        yield 'alphanumeric' => ['abc123', '/^[a-z0-9]+$/'];
        yield 'starts with capital' => ['Hello', '/^[A-Z]/'];
        yield 'ends with number' => ['test123', '/\d+$/'];
        yield 'contains word' => ['hello world', '/world/'];
        yield 'ip address pattern' => ['192.168.1.1', '/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/'];
        yield 'uuid pattern' => ['550e8400-e29b-41d4-a716-446655440000', '/^[a-f0-9-]{36}$/'];
        yield 'url pattern' => ['https://example.com', '/^https?:\/\//'];
    }

    #[Test]
    #[DataProvider('provideNonMatchingPatterns')]
    public function it_returns_false_when_pattern_does_not_match(
        string $actual,
        string $pattern
    ): void {
        // Arrange
        $operator = new RegexOperator();

        // Act
        $result = $operator->evaluate($actual, $pattern);

        // Assert
        self::assertFalse($result);
    }

    public static function provideNonMatchingPatterns(): iterable
    {
        yield 'phone number different prefix' => ['+44-555-1234', '/^\+1/'];
        yield 'not digits' => ['abc', '/^\d+$/'];
        yield 'contains spaces' => ['hello world', '/^\w+$/'];
        yield 'wrong email format' => ['invalid-email', '/^[a-z]+@[a-z]+\.[a-z]+$/'];
        yield 'starts with lowercase' => ['hello', '/^[A-Z]/'];
        yield 'no numbers at end' => ['test', '/\d+$/'];
    }

    #[Test]
    #[DataProvider('provideNonStringValues')]
    public function it_returns_false_for_non_string_values(mixed $actual, mixed $expected): void
    {
        // Arrange
        $operator = new RegexOperator();

        // Act
        $result = $operator->evaluate($actual, $expected);

        // Assert
        self::assertFalse($result);
    }

    public static function provideNonStringValues(): iterable
    {
        yield 'integer actual' => [123, '/\d+/'];
        yield 'integer pattern' => ['test', 123];
        yield 'null actual' => [null, '/test/'];
        yield 'null pattern' => ['test', null];
        yield 'boolean actual' => [true, '/test/'];
        yield 'boolean pattern' => ['test', false];
        yield 'array actual' => [['test'], '/test/'];
        yield 'array pattern' => ['test', ['/test/']];
        yield 'both integers' => [123, 123];
        yield 'both null' => [null, null];
    }

    #[Test]
    public function it_returns_false_for_invalid_regex_pattern(): void
    {
        // Arrange
        $operator = new RegexOperator();

        // Act & Assert - invalid patterns return false (suppressed with @)
        self::assertFalse($operator->evaluate('test', '/[/'));
        self::assertFalse($operator->evaluate('test', 'invalid'));
        self::assertFalse($operator->evaluate('test', ''));
        self::assertFalse($operator->evaluate('test', '/(?/'));
    }

    #[Test]
    public function it_handles_case_sensitive_patterns(): void
    {
        // Arrange
        $operator = new RegexOperator();

        // Act & Assert
        self::assertTrue($operator->evaluate('Hello', '/Hello/'));
        self::assertFalse($operator->evaluate('Hello', '/hello/'));
        self::assertTrue($operator->evaluate('TEST', '/TEST/'));
        self::assertFalse($operator->evaluate('TEST', '/test/'));
    }

    #[Test]
    public function it_handles_case_insensitive_patterns(): void
    {
        // Arrange
        $operator = new RegexOperator();

        // Act & Assert
        self::assertTrue($operator->evaluate('Hello', '/hello/i'));
        self::assertTrue($operator->evaluate('TEST', '/test/i'));
        self::assertTrue($operator->evaluate('MiXeD', '/mixed/i'));
    }

    #[Test]
    public function it_handles_unicode_patterns(): void
    {
        // Arrange
        $operator = new RegexOperator();

        // Act & Assert
        self::assertTrue($operator->evaluate('Привет', '/Привет/'));
        self::assertTrue($operator->evaluate('日本語', '/日本語/'));
        self::assertTrue($operator->evaluate('emoji 😀', '/😀/'));
        self::assertTrue($operator->evaluate('Привет', '/^[А-Яа-я]+$/u'));
    }

    #[Test]
    public function it_handles_special_regex_characters(): void
    {
        // Arrange
        $operator = new RegexOperator();

        // Act & Assert
        self::assertTrue($operator->evaluate('test.com', '/test\.com/'));
        self::assertTrue($operator->evaluate('test*', '/test\*/'));
        self::assertTrue($operator->evaluate('test+', '/test\+/'));
        self::assertTrue($operator->evaluate('test?', '/test\?/'));
        self::assertTrue($operator->evaluate('test[0]', '/test\[0\]/'));
    }

    #[Test]
    public function it_handles_anchors(): void
    {
        // Arrange
        $operator = new RegexOperator();

        // Act & Assert
        self::assertTrue($operator->evaluate('hello', '/^hello$/'));
        self::assertTrue($operator->evaluate('hello', '/^hello/'));
        self::assertTrue($operator->evaluate('hello', '/hello$/'));
        self::assertFalse($operator->evaluate('hello world', '/^hello$/'));
        self::assertTrue($operator->evaluate('hello world', '/^hello/'));
    }

    #[Test]
    public function it_handles_quantifiers(): void
    {
        // Arrange
        $operator = new RegexOperator();

        // Act & Assert
        self::assertTrue($operator->evaluate('aaa', '/a+/'));
        self::assertTrue($operator->evaluate('aaa', '/a{3}/'));
        self::assertTrue($operator->evaluate('aa', '/a{2,4}/'));
        self::assertTrue($operator->evaluate('test', '/t.*t/'));
        self::assertFalse($operator->evaluate('a', '/a{3}/'));
    }

    #[Test]
    public function it_handles_character_classes(): void
    {
        // Arrange
        $operator = new RegexOperator();

        // Act & Assert
        self::assertTrue($operator->evaluate('5', '/[0-9]/'));
        self::assertTrue($operator->evaluate('a', '/[a-z]/'));
        self::assertTrue($operator->evaluate('A', '/[A-Z]/'));
        self::assertTrue($operator->evaluate('abc123', '/^[a-z0-9]+$/'));
        self::assertFalse($operator->evaluate('ABC', '/[a-z]/'));
    }

    #[Test]
    public function it_handles_groups_and_alternation(): void
    {
        // Arrange
        $operator = new RegexOperator();

        // Act & Assert
        self::assertTrue($operator->evaluate('cat', '/cat|dog/'));
        self::assertTrue($operator->evaluate('dog', '/cat|dog/'));
        self::assertFalse($operator->evaluate('bird', '/cat|dog/'));
        self::assertTrue($operator->evaluate('test123', '/(test)\d+/'));
    }

    #[Test]
    public function it_handles_phone_number_example(): void
    {
        // Arrange
        $operator = new RegexOperator();

        // Act & Assert - from class documentation
        self::assertTrue($operator->evaluate('+1-555-1234', '/^\+1/'));
        self::assertFalse($operator->evaluate('+44-555-1234', '/^\+1/'));
    }

    #[Test]
    public function it_handles_email_validation(): void
    {
        // Arrange
        $operator = new RegexOperator();
        $emailPattern = '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/';

        // Act & Assert
        self::assertTrue($operator->evaluate('user@example.com', $emailPattern));
        self::assertTrue($operator->evaluate('test.user@subdomain.example.com', $emailPattern));
        self::assertFalse($operator->evaluate('invalid-email', $emailPattern));
        self::assertFalse($operator->evaluate('@example.com', $emailPattern));
    }

    #[Test]
    public function it_handles_multiline_mode(): void
    {
        // Arrange
        $operator = new RegexOperator();

        // Act & Assert
        self::assertTrue($operator->evaluate("line1\nline2", '/^line2/m'));
        self::assertFalse($operator->evaluate("line1\nline2", '/^line2/'));
    }

    #[Test]
    public function it_returns_false_when_preg_match_returns_zero(): void
    {
        // Arrange
        $operator = new RegexOperator();

        // Act & Assert
        self::assertFalse($operator->evaluate('test', '/nomatch/'));
        self::assertFalse($operator->evaluate('', '/test/'));
    }
}
