<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Tests\Context;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pulse\Flags\Core\Context\ContextInterface;
use Pulse\Flags\Core\Context\SegmentContext;

final class SegmentContextTest extends TestCase
{
    #[Test]
    public function it_can_be_created_with_user_id_only(): void
    {
        // Arrange
        $userId = 'user-123';

        // Act
        $context = new SegmentContext(userId: $userId);

        // Assert
        self::assertInstanceOf(SegmentContext::class, $context);
        self::assertSame($userId, $context->getUserId());
        self::assertSame([], $context->getAttributes());
    }

    #[Test]
    public function it_can_be_created_with_user_id_and_attributes(): void
    {
        // Arrange
        $userId = 'user-456';
        $attributes = [
            'email' => 'user@example.com',
            'country' => 'US',
        ];

        // Act
        $context = new SegmentContext(
            userId: $userId,
            attributes: $attributes
        );

        // Assert
        self::assertSame($userId, $context->getUserId());
        self::assertSame($attributes, $context->getAttributes());
    }

    #[Test]
    public function it_implements_context_interface(): void
    {
        // Arrange
        $context = new SegmentContext(userId: 'user-123');

        // Act & Assert
        self::assertInstanceOf(ContextInterface::class, $context);
    }

    #[Test]
    public function it_can_get_attribute_by_key(): void
    {
        // Arrange
        $context = new SegmentContext(
            userId: 'user-123',
            attributes: [
                'email' => 'test@example.com',
                'age' => 30,
            ]
        );

        // Act
        $email = $context->get('email');
        $age = $context->get('age');

        // Assert
        self::assertSame('test@example.com', $email);
        self::assertSame(30, $age);
    }

    #[Test]
    public function it_returns_null_for_non_existent_attribute(): void
    {
        // Arrange
        $context = new SegmentContext(
            userId: 'user-123',
            attributes: ['key' => 'value']
        );

        // Act
        $result = $context->get('non_existent');

        // Assert
        self::assertNull($result);
    }

    #[Test]
    public function it_returns_default_value_for_non_existent_attribute(): void
    {
        // Arrange
        $context = new SegmentContext(
            userId: 'user-123',
            attributes: ['key' => 'value']
        );

        // Act
        $result = $context->get('non_existent', 'default');

        // Assert
        self::assertSame('default', $result);
    }

    #[Test]
    public function it_can_check_if_attribute_exists(): void
    {
        // Arrange
        $context = new SegmentContext(
            userId: 'user-123',
            attributes: [
                'exists' => 'value',
                'null_value' => null,
            ]
        );

        // Act & Assert
        self::assertTrue($context->has('exists'));
        self::assertTrue($context->has('null_value'));
        self::assertFalse($context->has('not_exists'));
    }

    #[Test]
    public function it_converts_to_array_with_user_id_first(): void
    {
        // Arrange
        $userId = 'user-789';
        $attributes = [
            'email' => 'test@example.com',
            'country' => 'US',
        ];
        $context = new SegmentContext(
            userId: $userId,
            attributes: $attributes
        );

        // Act
        $result = $context->toArray();

        // Assert
        self::assertSame([
            'user_id' => $userId,
            'email' => 'test@example.com',
            'country' => 'US',
        ], $result);

        // Verify user_id is first
        $keys = array_keys($result);
        self::assertSame('user_id', $keys[0]);
    }

    #[Test]
    public function it_converts_to_array_with_only_user_id_when_no_attributes(): void
    {
        // Arrange
        $context = new SegmentContext(userId: 'user-123');

        // Act
        $result = $context->toArray();

        // Assert
        self::assertSame(['user_id' => 'user-123'], $result);
    }

    #[Test]
    public function it_handles_user_id_in_attributes_correctly(): void
    {
        // Arrange - attributes contain user_id
        $context = new SegmentContext(
            userId: 'correct-user-id',
            attributes: [
                'user_id' => 'from-attributes',
                'other' => 'value',
            ]
        );

        // Act
        $result = $context->toArray();

        // Assert - both user_id values are merged (constructor first, then attributes)
        // array_merge keeps the last value for duplicate keys
        self::assertArrayHasKey('user_id', $result);
        self::assertSame('value', $result['other']);
    }

    #[Test]
    #[DataProvider('provideAttributeTypes')]
    public function it_handles_various_attribute_types(
        string $key,
        mixed $value
    ): void {
        // Arrange
        $context = new SegmentContext(
            userId: 'user-123',
            attributes: [$key => $value]
        );

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
        yield 'zero' => ['key', 0];
    }

    #[Test]
    public function it_handles_unicode_in_user_id_and_attributes(): void
    {
        // Arrange
        $context = new SegmentContext(
            userId: 'пользователь-123',
            attributes: [
                'имя' => 'Иван',
                'страна' => 'Россия',
            ]
        );

        // Act & Assert
        self::assertSame('пользователь-123', $context->getUserId());
        self::assertSame('Иван', $context->get('имя'));
        self::assertSame('Россия', $context->get('страна'));
    }

    #[Test]
    public function it_handles_special_characters_in_user_id(): void
    {
        // Arrange
        $userId = 'user-!@#$%^&*()';

        // Act
        $context = new SegmentContext(userId: $userId);

        // Assert
        self::assertSame($userId, $context->getUserId());
    }

    #[Test]
    public function it_handles_empty_string_as_user_id(): void
    {
        // Arrange
        $userId = '';

        // Act
        $context = new SegmentContext(userId: $userId);

        // Assert
        self::assertSame('', $context->getUserId());
    }

    #[Test]
    public function it_handles_nested_attributes(): void
    {
        // Arrange
        $attributes = [
            'user_details' => [
                'name' => 'John',
                'address' => [
                    'city' => 'New York',
                    'country' => 'US',
                ],
            ],
        ];
        $context = new SegmentContext(
            userId: 'user-123',
            attributes: $attributes
        );

        // Act
        $userDetails = $context->get('user_details');

        // Assert
        self::assertSame($attributes['user_details'], $userDetails);
        self::assertSame('John', $userDetails['name']);
        self::assertSame('US', $userDetails['address']['country']);
    }

    #[Test]
    public function it_distinguishes_between_null_value_and_missing_key(): void
    {
        // Arrange
        $context = new SegmentContext(
            userId: 'user-123',
            attributes: [
                'explicit_null' => null,
            ]
        );

        // Act & Assert
        self::assertTrue($context->has('explicit_null'));
        self::assertNull($context->get('explicit_null'));

        self::assertFalse($context->has('missing_key'));
        self::assertNull($context->get('missing_key'));
    }

    #[Test]
    public function it_returns_consistent_array_on_multiple_calls(): void
    {
        // Arrange
        $context = new SegmentContext(
            userId: 'user-123',
            attributes: ['key' => 'value']
        );

        // Act
        $array1 = $context->toArray();
        $array2 = $context->toArray();

        // Assert
        self::assertSame($array1, $array2);
    }

    #[Test]
    public function it_preserves_attribute_order_in_to_array(): void
    {
        // Arrange
        $attributes = [
            'z' => 1,
            'a' => 2,
            'm' => 3,
        ];
        $context = new SegmentContext(
            userId: 'user-123',
            attributes: $attributes
        );

        // Act
        $result = $context->toArray();

        // Assert - user_id should be first, then attributes in original order
        $keys = array_keys($result);
        self::assertSame('user_id', $keys[0]);
        self::assertSame('z', $keys[1]);
        self::assertSame('a', $keys[2]);
        self::assertSame('m', $keys[3]);
    }

    #[Test]
    public function it_handles_numeric_string_keys_in_attributes(): void
    {
        // Arrange
        $context = new SegmentContext(
            userId: 'user-123',
            attributes: [
                '0' => 'first',
                '1' => 'second',
                '100' => 'hundred',
            ]
        );

        // Act & Assert
        self::assertSame('first', $context->get('0'));
        self::assertSame('second', $context->get('1'));
        self::assertSame('hundred', $context->get('100'));
    }

    #[Test]
    public function it_handles_email_as_user_id(): void
    {
        // Arrange
        $userId = 'user@example.com';

        // Act
        $context = new SegmentContext(userId: $userId);

        // Assert
        self::assertSame($userId, $context->getUserId());
    }

    #[Test]
    public function it_handles_uuid_as_user_id(): void
    {
        // Arrange
        $userId = '550e8400-e29b-41d4-a716-446655440000';

        // Act
        $context = new SegmentContext(userId: $userId);

        // Assert
        self::assertSame($userId, $context->getUserId());
    }

    #[Test]
    public function it_handles_very_long_user_id(): void
    {
        // Arrange
        $userId = str_repeat('a', 1000);

        // Act
        $context = new SegmentContext(userId: $userId);

        // Assert
        self::assertSame($userId, $context->getUserId());
        self::assertSame(1000, strlen($context->getUserId()));
    }

    #[Test]
    public function it_handles_many_attributes(): void
    {
        // Arrange
        $attributes = [];
        for ($i = 0; $i < 100; $i++) {
            $attributes["attr_{$i}"] = "value_{$i}";
        }
        $context = new SegmentContext(
            userId: 'user-123',
            attributes: $attributes
        );

        // Act
        $result = $context->toArray();

        // Assert
        self::assertCount(101, $result); // 100 attributes + user_id
        self::assertSame('user-123', $result['user_id']);
        self::assertSame('value_0', $result['attr_0']);
        self::assertSame('value_99', $result['attr_99']);
    }

    #[Test]
    public function get_attributes_returns_only_attributes_without_user_id(): void
    {
        // Arrange
        $attributes = ['email' => 'test@example.com', 'age' => 30];
        $context = new SegmentContext(
            userId: 'user-123',
            attributes: $attributes
        );

        // Act
        $result = $context->getAttributes();

        // Assert
        self::assertSame($attributes, $result);
        self::assertArrayNotHasKey('user_id', $result);
    }

    #[Test]
    public function to_array_includes_user_id_but_get_attributes_does_not(): void
    {
        // Arrange
        $context = new SegmentContext(
            userId: 'user-123',
            attributes: ['key' => 'value']
        );

        // Act
        $toArray = $context->toArray();
        $getAttributes = $context->getAttributes();

        // Assert
        self::assertArrayHasKey('user_id', $toArray);
        self::assertArrayNotHasKey('user_id', $getAttributes);
        self::assertCount(2, $toArray);
        self::assertCount(1, $getAttributes);
    }
}
