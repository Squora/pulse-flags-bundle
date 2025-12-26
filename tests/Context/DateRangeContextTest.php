<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Tests\Context;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pulse\Flags\Core\Context\ContextInterface;
use Pulse\Flags\Core\Context\DateRangeContext;

final class DateRangeContextTest extends TestCase
{
    #[Test]
    public function it_can_be_created_with_datetime(): void
    {
        // Arrange
        $date = new DateTimeImmutable('2025-01-15 10:30:00');

        // Act
        $context = new DateRangeContext(currentDate: $date);

        // Assert
        self::assertInstanceOf(DateRangeContext::class, $context);
        self::assertSame($date, $context->getCurrentDate());
    }

    #[Test]
    public function it_implements_context_interface(): void
    {
        // Arrange
        $date = new DateTimeImmutable();
        $context = new DateRangeContext(currentDate: $date);

        // Act & Assert
        self::assertInstanceOf(ContextInterface::class, $context);
    }

    #[Test]
    public function it_converts_to_array_with_datetime_object(): void
    {
        // Arrange
        $date = new DateTimeImmutable('2025-12-22 15:45:30');
        $context = new DateRangeContext(currentDate: $date);

        // Act
        $result = $context->toArray();

        // Assert
        self::assertIsArray($result);
        self::assertArrayHasKey('current_date', $result);
        self::assertSame($date, $result['current_date']);
        self::assertInstanceOf(DateTimeImmutable::class, $result['current_date']);
    }

    #[Test]
    public function it_preserves_datetime_immutability(): void
    {
        // Arrange
        $originalDate = new DateTimeImmutable('2025-01-01 00:00:00');
        $context = new DateRangeContext(currentDate: $originalDate);

        // Act
        $retrievedDate = $context->getCurrentDate();
        $modifiedDate = $retrievedDate->modify('+1 day');

        // Assert
        self::assertSame($originalDate, $context->getCurrentDate());
        self::assertNotSame($modifiedDate, $context->getCurrentDate());
        self::assertEquals('2025-01-01', $originalDate->format('Y-m-d'));
    }

    #[Test]
    #[DataProvider('provideDateTimeScenarios')]
    public function it_handles_various_datetime_values(
        string $dateString,
        string $expectedFormat
    ): void {
        // Arrange
        $date = new DateTimeImmutable($dateString);
        $context = new DateRangeContext(currentDate: $date);

        // Act
        $result = $context->getCurrentDate();

        // Assert
        self::assertEquals($expectedFormat, $result->format('Y-m-d H:i:s'));
    }

    public static function provideDateTimeScenarios(): iterable
    {
        yield 'specific date and time' => [
            'dateString' => '2025-12-22 14:30:00',
            'expectedFormat' => '2025-12-22 14:30:00',
        ];

        yield 'midnight' => [
            'dateString' => '2025-01-01 00:00:00',
            'expectedFormat' => '2025-01-01 00:00:00',
        ];

        yield 'end of day' => [
            'dateString' => '2025-12-31 23:59:59',
            'expectedFormat' => '2025-12-31 23:59:59',
        ];

        yield 'leap year date' => [
            'dateString' => '2024-02-29 12:00:00',
            'expectedFormat' => '2024-02-29 12:00:00',
        ];

        yield 'past date' => [
            'dateString' => '2000-01-01 00:00:00',
            'expectedFormat' => '2000-01-01 00:00:00',
        ];

        yield 'future date' => [
            'dateString' => '2099-12-31 23:59:59',
            'expectedFormat' => '2099-12-31 23:59:59',
        ];
    }

    #[Test]
    public function it_handles_timezone_aware_dates(): void
    {
        // Arrange
        $date = new DateTimeImmutable('2025-12-22 10:00:00', new \DateTimeZone('America/New_York'));
        $context = new DateRangeContext(currentDate: $date);

        // Act
        $result = $context->getCurrentDate();

        // Assert
        self::assertEquals('America/New_York', $result->getTimezone()->getName());
    }

    #[Test]
    public function it_preserves_microseconds(): void
    {
        // Arrange
        $date = new DateTimeImmutable('2025-12-22 10:00:00.123456');
        $context = new DateRangeContext(currentDate: $date);

        // Act
        $result = $context->getCurrentDate();

        // Assert
        self::assertEquals('123456', $result->format('u'));
    }

    #[Test]
    public function it_returns_same_datetime_instance_on_multiple_calls(): void
    {
        // Arrange
        $date = new DateTimeImmutable('2025-12-22 10:00:00');
        $context = new DateRangeContext(currentDate: $date);

        // Act
        $result1 = $context->getCurrentDate();
        $result2 = $context->getCurrentDate();

        // Assert
        self::assertSame($result1, $result2);
    }

    #[Test]
    public function it_returns_consistent_array_on_multiple_calls(): void
    {
        // Arrange
        $date = new DateTimeImmutable('2025-12-22 10:00:00');
        $context = new DateRangeContext(currentDate: $date);

        // Act
        $array1 = $context->toArray();
        $array2 = $context->toArray();

        // Assert
        self::assertSame($array1['current_date'], $array2['current_date']);
    }

    #[Test]
    public function it_works_with_now(): void
    {
        // Arrange
        $now = new DateTimeImmutable('now');
        $context = new DateRangeContext(currentDate: $now);

        // Act
        $result = $context->getCurrentDate();

        // Assert
        self::assertInstanceOf(DateTimeImmutable::class, $result);
        self::assertSame($now, $result);
    }

    #[Test]
    public function it_works_with_relative_date_formats(): void
    {
        // Arrange
        $date = new DateTimeImmutable('+1 week');
        $context = new DateRangeContext(currentDate: $date);

        // Act
        $result = $context->getCurrentDate();

        // Assert
        self::assertInstanceOf(DateTimeImmutable::class, $result);
        self::assertSame($date, $result);
    }

    #[Test]
    public function it_handles_unix_epoch(): void
    {
        // Arrange
        $date = new DateTimeImmutable('@0');
        $context = new DateRangeContext(currentDate: $date);

        // Act
        $result = $context->getCurrentDate();

        // Assert
        self::assertEquals('1970-01-01 00:00:00', $result->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function it_works_with_iso8601_format(): void
    {
        // Arrange
        $date = new DateTimeImmutable('2025-12-22T14:30:00+00:00');
        $context = new DateRangeContext(currentDate: $date);

        // Act
        $result = $context->getCurrentDate();

        // Assert
        self::assertEquals('2025-12-22T14:30:00+00:00', $result->format('c'));
    }

    #[Test]
    public function it_maintains_date_precision(): void
    {
        // Arrange
        $date = new DateTimeImmutable('2025-12-22 14:30:45.987654');
        $context = new DateRangeContext(currentDate: $date);

        // Act
        $result = $context->getCurrentDate();

        // Assert
        self::assertEquals('2025-12-22 14:30:45.987654', $result->format('Y-m-d H:i:s.u'));
    }

    #[Test]
    public function different_instances_with_same_date_are_equal(): void
    {
        // Arrange
        $date1 = new DateTimeImmutable('2025-12-22 10:00:00');
        $date2 = new DateTimeImmutable('2025-12-22 10:00:00');
        $context1 = new DateRangeContext(currentDate: $date1);
        $context2 = new DateRangeContext(currentDate: $date2);

        // Act & Assert
        self::assertEquals($context1->getCurrentDate(), $context2->getCurrentDate());
        self::assertNotSame($context1, $context2);
    }
}
