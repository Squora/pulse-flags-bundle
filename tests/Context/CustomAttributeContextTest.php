<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Tests\Context;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pulse\Flags\Core\Context\ContextInterface;
use Pulse\Flags\Core\Context\CustomAttributeContext;

final class CustomAttributeContextTest extends TestCase
{
    #[Test]
    public function it_can_be_created_with_empty_attributes(): void
    {
        // Arrange & Act
        $context = new CustomAttributeContext();

        // Assert
        self::assertInstanceOf(CustomAttributeContext::class, $context);
        self::assertSame([], $context->getAttributes());
    }

    #[Test]
    public function it_can_be_created_with_attributes(): void
    {
        // Arrange
        $attributes = [
            'email' => 'user@example.com',
            'age' => 25,
            'premium' => true,
        ];

        // Act
        $context = new CustomAttributeContext(attributes: $attributes);

        // Assert
        self::assertSame($attributes, $context->getAttributes());
    }

    #[Test]
    public function it_implements_context_interface(): void
    {
        // Arrange
        $context = new CustomAttributeContext();

        // Act & Assert
        self::assertInstanceOf(ContextInterface::class, $context);
    }

    #[Test]
    public function it_can_get_attribute_by_key(): void
    {
        // Arrange
        $context = new CustomAttributeContext(attributes: [
            'name' => 'John',
            'age' => 30,
        ]);

        // Act
        $name = $context->get('name');
        $age = $context->get('age');

        // Assert
        self::assertSame('John', $name);
        self::assertSame(30, $age);
    }

    #[Test]
    public function it_returns_null_for_non_existent_key(): void
    {
        // Arrange
        $context = new CustomAttributeContext(attributes: ['key' => 'value']);

        // Act
        $result = $context->get('non_existent');

        // Assert
        self::assertNull($result);
    }

    #[Test]
    public function it_returns_default_value_for_non_existent_key(): void
    {
        // Arrange
        $context = new CustomAttributeContext(attributes: ['key' => 'value']);

        // Act
        $result = $context->get('non_existent', 'default');

        // Assert
        self::assertSame('default', $result);
    }

    #[Test]
    public function it_can_check_if_attribute_exists(): void
    {
        // Arrange
        $context = new CustomAttributeContext(attributes: [
            'exists' => 'value',
            'null_value' => null,
        ]);

        // Act & Assert
        self::assertTrue($context->has('exists'));
        self::assertTrue($context->has('null_value'));
        self::assertFalse($context->has('not_exists'));
    }

    #[Test]
    public function it_converts_to_array_correctly(): void
    {
        // Arrange
        $attributes = [
            'key1' => 'value1',
            'key2' => 'value2',
        ];
        $context = new CustomAttributeContext(attributes: $attributes);

        // Act
        $result = $context->toArray();

        // Assert
        self::assertSame($attributes, $result);
    }

    #[Test]
    public function it_returns_empty_array_when_no_attributes(): void
    {
        // Arrange
        $context = new CustomAttributeContext();

        // Act
        $result = $context->toArray();

        // Assert
        self::assertSame([], $result);
    }

    #[Test]
    #[DataProvider('provideAttributeTypes')]
    public function it_handles_various_attribute_types(
        string $key,
        mixed $value
    ): void {
        // Arrange
        $context = new CustomAttributeContext(attributes: [$key => $value]);

        // Act
        $result = $context->get($key);

        // Assert
        self::assertSame($value, $result);
    }

    public static function provideAttributeTypes(): iterable
    {
        yield 'string value' => ['key', 'string value'];
        yield 'integer value' => ['key', 123];
        yield 'float value' => ['key', 3.14];
        yield 'boolean true' => ['key', true];
        yield 'boolean false' => ['key', false];
        yield 'null value' => ['key', null];
        yield 'array value' => ['key', ['nested', 'array']];
        yield 'empty string' => ['key', ''];
        yield 'zero integer' => ['key', 0];
        yield 'zero float' => ['key', 0.0];
    }

    #[Test]
    public function it_handles_nested_arrays(): void
    {
        // Arrange
        $attributes = [
            'user' => [
                'name' => 'John',
                'details' => [
                    'age' => 30,
                    'email' => 'john@example.com',
                ],
            ],
        ];
        $context = new CustomAttributeContext(attributes: $attributes);

        // Act
        $user = $context->get('user');

        // Assert
        self::assertSame($attributes['user'], $user);
        self::assertSame('John', $user['name']);
        self::assertSame(30, $user['details']['age']);
    }

    #[Test]
    public function it_handles_unicode_keys_and_values(): void
    {
        // Arrange
        $attributes = [
            'имя' => 'Иван',
            '名前' => '太郎',
            'emoji_🎉' => 'celebration',
        ];
        $context = new CustomAttributeContext(attributes: $attributes);

        // Act & Assert
        self::assertSame('Иван', $context->get('имя'));
        self::assertSame('太郎', $context->get('名前'));
        self::assertSame('celebration', $context->get('emoji_🎉'));
    }

    #[Test]
    public function it_handles_special_characters_in_keys(): void
    {
        // Arrange
        $attributes = [
            'key-with-dash' => 'value1',
            'key.with.dot' => 'value2',
            'key_with_underscore' => 'value3',
            'key with space' => 'value4',
        ];
        $context = new CustomAttributeContext(attributes: $attributes);

        // Act & Assert
        self::assertSame('value1', $context->get('key-with-dash'));
        self::assertSame('value2', $context->get('key.with.dot'));
        self::assertSame('value3', $context->get('key_with_underscore'));
        self::assertSame('value4', $context->get('key with space'));
    }

    #[Test]
    public function it_preserves_attribute_order(): void
    {
        // Arrange
        $attributes = [
            'z' => 1,
            'a' => 2,
            'm' => 3,
        ];
        $context = new CustomAttributeContext(attributes: $attributes);

        // Act
        $result = $context->getAttributes();

        // Assert
        self::assertSame(array_keys($attributes), array_keys($result));
    }

    #[Test]
    public function it_handles_numeric_string_keys(): void
    {
        // Arrange
        $attributes = [
            '0' => 'first',
            '1' => 'second',
            '100' => 'hundred',
        ];
        $context = new CustomAttributeContext(attributes: $attributes);

        // Act & Assert
        self::assertSame('first', $context->get('0'));
        self::assertSame('second', $context->get('1'));
        self::assertSame('hundred', $context->get('100'));
    }

    #[Test]
    public function it_distinguishes_between_null_value_and_missing_key(): void
    {
        // Arrange
        $context = new CustomAttributeContext(attributes: [
            'explicit_null' => null,
        ]);

        // Act & Assert - has() uses array_key_exists
        self::assertTrue($context->has('explicit_null'));
        self::assertNull($context->get('explicit_null'));

        self::assertFalse($context->has('missing_key'));
        self::assertNull($context->get('missing_key'));
    }

    #[Test]
    public function it_returns_default_when_key_has_null_value(): void
    {
        // Arrange
        $context = new CustomAttributeContext(attributes: [
            'null_value' => null,
        ]);

        // Act
        $result = $context->get('null_value', 'default');

        // Assert - get() uses ?? operator, so default is returned for null
        self::assertSame('default', $result);
    }

    #[Test]
    public function it_returns_consistent_array_on_multiple_calls(): void
    {
        // Arrange
        $context = new CustomAttributeContext(attributes: [
            'key1' => 'value1',
            'key2' => 'value2',
        ]);

        // Act
        $array1 = $context->toArray();
        $array2 = $context->toArray();
        $attributes1 = $context->getAttributes();
        $attributes2 = $context->getAttributes();

        // Assert
        self::assertSame($array1, $array2);
        self::assertSame($attributes1, $attributes2);
    }

    #[Test]
    public function it_handles_very_long_attribute_values(): void
    {
        // Arrange
        $longValue = str_repeat('x', 10000);
        $context = new CustomAttributeContext(attributes: [
            'long_value' => $longValue,
        ]);

        // Act
        $result = $context->get('long_value');

        // Assert
        self::assertSame($longValue, $result);
        self::assertSame(10000, strlen($result));
    }

    #[Test]
    public function it_handles_many_attributes(): void
    {
        // Arrange
        $attributes = [];
        for ($i = 0; $i < 1000; $i++) {
            $attributes["key_{$i}"] = "value_{$i}";
        }
        $context = new CustomAttributeContext(attributes: $attributes);

        // Act & Assert
        self::assertCount(1000, $context->getAttributes());
        self::assertSame('value_0', $context->get('key_0'));
        self::assertSame('value_999', $context->get('key_999'));
    }

    #[Test]
    public function it_handles_object_values(): void
    {
        // Arrange
        $object = new \stdClass();
        $object->property = 'value';

        $context = new CustomAttributeContext(attributes: [
            'object' => $object,
        ]);

        // Act
        $result = $context->get('object');

        // Assert
        self::assertSame($object, $result);
        self::assertSame('value', $result->property);
    }

    #[Test]
    public function to_array_returns_same_as_get_attributes(): void
    {
        // Arrange
        $attributes = ['key' => 'value'];
        $context = new CustomAttributeContext(attributes: $attributes);

        // Act
        $toArray = $context->toArray();
        $getAttributes = $context->getAttributes();

        // Assert
        self::assertSame($toArray, $getAttributes);
    }

    #[Test]
    public function it_handles_empty_string_as_key(): void
    {
        // Arrange
        $context = new CustomAttributeContext(attributes: [
            '' => 'empty key value',
        ]);

        // Act
        $result = $context->get('');

        // Assert
        self::assertSame('empty key value', $result);
        self::assertTrue($context->has(''));
    }
}
