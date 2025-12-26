# Test Code Review Checklist

Используйте этот чек-лист при написании и review тестов.

## ✅ Структура и организация

- [ ] Тест находится в правильной директории (`tests/{Namespace}/`)
- [ ] Имя класса соответствует паттерну `{ClassName}Test`
- [ ] Namespace соответствует структуре (`Pulse\Flags\Core\Tests\{Namespace}`)
- [ ] Используется `declare(strict_types=1)`
- [ ] Класс отмечен как `final`
- [ ] Все необходимые импорты присутствуют

## ✅ Структура тестов (AAA)

- [ ] Каждый тест следует AAA паттерну:
  - **Arrange**: подготовка данных и зависимостей
  - **Act**: выполнение тестируемого действия
  - **Assert**: проверка результатов
- [ ] Секции разделены комментариями или пустыми строками
- [ ] Логика теста понятна и линейна

## ✅ Именование

- [ ] Имена тестов описательные и понятные
- [ ] Используется паттерн: `it_{should/can/returns/throws}_*_when_*`
- [ ] Data provider cases имеют описательные имена
- [ ] Переменные имеют говорящие имена (`$sut`, `$expectedResult`, etc.)
- [ ] Избегаем сокращений (кроме общепринятых: `$sut`, `$dto`, etc.)

### Примеры хороших имен тестов:
```php
it_can_be_created_with_valid_parameters()
it_returns_true_when_user_is_in_whitelist()
it_throws_exception_when_percentage_exceeds_100()
it_merges_contexts_in_correct_order()
it_handles_null_values_gracefully()
```

### Примеры плохих имен:
```php
test1() // ❌ Не описательно
testUserContext() // ❌ Слишком общее
testIsEnabledReturnsTrueForValidUser() // ❌ Старый стиль
```

## ✅ Assertions

- [ ] Используется `self::assertSame()` вместо `assertEquals()` где возможно
- [ ] Используются специфичные assertions (`assertCount`, `assertEmpty`, `assertArrayHasKey`, etc.)
- [ ] Добавлены описания к assertions где это улучшает понимание
- [ ] Избегаем `assertTrue()` и `assertFalse()` где есть более специфичные варианты
- [ ] Проверяется не только "happy path", но и edge cases

### Примеры:
```php
// ✅ Хорошо
self::assertSame('expected', $result);
self::assertCount(3, $items);
self::assertEmpty($list);
self::assertInstanceOf(Context::class, $context);

// ❌ Плохо
self::assertEquals('expected', $result); // Нестрогое сравнение
self::assertTrue(count($items) === 3); // Используйте assertCount
self::assertTrue(empty($list)); // Используйте assertEmpty
```

## ✅ Data Providers

- [ ] Используются для параметризованных тестов (3+ сценария)
- [ ] Имя метода: `provide{Description}` или `provide{TestMethodName}Data`
- [ ] Отмечен атрибутом `public static`
- [ ] Возвращает `iterable`
- [ ] Каждый case имеет описательное имя через `yield 'name' => [...]`
- [ ] Параметры именованы понятно

### Пример:
```php
#[Test]
#[DataProvider('provideValidPercentages')]
public function it_validates_percentage_values(int $percentage, bool $expected): void
{
    // ...
}

public static function provideValidPercentages(): iterable
{
    yield 'zero percent is valid' => [
        'percentage' => 0,
        'expected' => true,
    ];

    yield 'fifty percent is valid' => [
        'percentage' => 50,
        'expected' => true,
    ];

    yield 'hundred percent is valid' => [
        'percentage' => 100,
        'expected' => true,
    ];

    yield 'negative percent is invalid' => [
        'percentage' => -1,
        'expected' => false,
    ];

    yield 'over hundred percent is invalid' => [
        'percentage' => 101,
        'expected' => false,
    ];
}
```

## ✅ Mocks и Stubs

- [ ] Используется `createMock()` для создания моков
- [ ] Моки создаются только для интерфейсов или абстрактных классов
- [ ] Установлены expectations (`expects()`, `method()`, `willReturn()`)
- [ ] Проверяются вызовы методов (`expects(self::once())`)
- [ ] Не используются моки там, где можно использовать реальные объекты

### Примеры:
```php
// ✅ Хорошо
$storage = $this->createMock(StorageInterface::class);
$storage->expects(self::once())
    ->method('get')
    ->with(self::equalTo('flag-name'))
    ->willReturn(['status' => 'enabled']);

// ❌ Плохо
$storage = $this->getMockBuilder(StorageInterface::class)
    ->getMock(); // Устаревший синтаксис
```

## ✅ Edge Cases и граничные значения

- [ ] Тестируются `null` значения
- [ ] Тестируются пустые строки и массивы
- [ ] Тестируются граничные значения (0, -1, MAX_INT, etc.)
- [ ] Тестируются unicode/multibyte строки
- [ ] Тестируются большие значения/датасеты
- [ ] Тестируются невалидные входные данные

### Обязательные edge cases для каждого типа:
```php
// Strings
- '' (пустая строка)
- ' ' (пробел)
- 'тест' (unicode)
- Очень длинная строка (>1000 символов)

// Numbers
- 0
- -1
- PHP_INT_MAX
- 0.0
- -0.0

// Arrays
- [] (пустой массив)
- [''] (массив с пустой строкой)
- Большой массив (>10000 элементов)

// Booleans
- true
- false

// null
- null
```

## ✅ Исключения

- [ ] Используется `expectException()` перед кодом, который должен выбросить исключение
- [ ] Проверяется тип исключения
- [ ] Проверяется сообщение исключения (если критично)
- [ ] Проверяется код ошибки (если используется)

### Пример:
```php
#[Test]
public function it_throws_validation_exception_when_config_is_invalid(): void
{
    // Arrange
    $this->expectException(ValidationException::class);
    $this->expectExceptionMessage('Percentage must be between 0 and 100');

    $validator = new PercentageStrategyValidator();

    // Act
    $validator->validate(['percentage' => 150]);

    // No assert needed - exception expectation is the assertion
}
```

## ✅ Изоляция тестов

- [ ] Каждый тест независим от других
- [ ] Тесты не зависят от порядка выполнения
- [ ] Используется `setUp()` для инициализации общих объектов
- [ ] Используется `tearDown()` для очистки ресурсов (файлы, соединения, etc.)
- [ ] Нет разделяемого состояния между тестами

### Пример:
```php
final class StorageTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/test_' . uniqid();
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            // Cleanup
            array_map('unlink', glob($this->tempDir . '/*'));
            rmdir($this->tempDir);
        }
        parent::tearDown();
    }
}
```

## ✅ Производительность

- [ ] Unit тесты выполняются быстро (<10ms каждый)
- [ ] Избегаем sleep(), долгих операций I/O в unit тестах
- [ ] Integration тесты отделены от unit тестов
- [ ] Используются in-memory альтернативы (SQLite) где возможно
- [ ] Performance-критичные компоненты имеют benchmark тесты

## ✅ Документация

- [ ] Сложные тесты имеют PHPDoc комментарии
- [ ] Объясняется "почему", а не "что" (код сам показывает "что")
- [ ] Ссылки на related issues/tickets если применимо
- [ ] Warning/Note комментарии где тест counterintuitive

### Пример:
```php
/**
 * This test verifies the deterministic behavior of hash calculation.
 * The same input MUST always produce the same hash for user consistency
 * across multiple requests (feature flag should not flicker).
 *
 * @see https://github.com/project/issues/123
 */
#[Test]
public function it_produces_same_hash_for_same_input_consistently(): void
{
    // ...
}
```

## ✅ Coverage

- [ ] Все public методы покрыты тестами
- [ ] Все ветки (if/else, switch/case) покрыты
- [ ] Все исключительные ситуации покрыты
- [ ] Private методы тестируются через public API
- [ ] Достигнут целевой % coverage для модуля

## ✅ Типы тестов

### Unit тесты
- [ ] Тестируется один класс изолированно
- [ ] Все зависимости замокированы
- [ ] Быстрые (<10ms)
- [ ] Нет I/O операций (файлы, БД, сеть)

### Integration тесты
- [ ] Тестируется взаимодействие компонентов
- [ ] Используются реальные зависимости где критично
- [ ] Могут быть медленнее (до 100-200ms)
- [ ] Отмечены как integration (группа/атрибут)

### Functional тесты
- [ ] Тестируется весь flow end-to-end
- [ ] Используется реальное окружение
- [ ] Отмечены как functional

## ✅ Специфичные проверки

### Для Context классов
- [ ] Тестируется создание с различными параметрами
- [ ] Тестируется метод `toArray()`
- [ ] Тестируются getter методы
- [ ] Тестируется обработка null значений
- [ ] Тестируются именованные конструкторы (если есть)

### Для Strategy классов
- [ ] Тестируется метод `isEnabled()` с различными конфигами
- [ ] Тестируется метод `getName()`
- [ ] Тестируются edge cases процентов/диапазонов
- [ ] Тестируется детерминированность (где применимо)
- [ ] Статистические тесты для Percentage/Hash стратегий

### Для Operator классов
- [ ] Тестируются различные типы данных (string, int, float, bool, null)
- [ ] Тестируется type coercion
- [ ] Тестируется case sensitivity
- [ ] Тестируются unicode строки

### Для Storage классов
- [ ] Тестируются все методы интерфейса
- [ ] Тестируется pagination
- [ ] Тестируется concurrent access
- [ ] Тестируется обработка ошибок (file permissions, corruption, etc.)
- [ ] Cleanup в tearDown

### Для Validator классов
- [ ] Тестируются валидные конфигурации
- [ ] Тестируются невалидные конфигурации
- [ ] Проверяются сообщения об ошибках
- [ ] Тестируются все правила валидации

## ✅ Code Style

- [ ] Соблюдается PSR-12
- [ ] Используются атрибуты PHP 8+ (`#[Test]`, `#[DataProvider]`)
- [ ] Используются typed properties
- [ ] Используются именованные параметры где улучшает читаемость
- [ ] Нет dead code
- [ ] Нет закомментированного кода
- [ ] Нет debug вызовов (var_dump, print_r, dd, etc.)

## ✅ Best Practices специфичные для проекта

### Pulse Flags Bundle
- [ ] Тесты для стратегий используют реальные Context объекты
- [ ] Процентные стратегии имеют статистические тесты
- [ ] Hash алгоритмы тестируются на детерминированность
- [ ] Storage тесты используют временные директории
- [ ] Command тесты используют CommandTester
- [ ] Все операторы имеют matrix тесты (data provider с всеми комбинациями)

## 📊 Метрики качества

После написания теста, убедитесь что:

- [ ] Coverage >= целевого для модуля (см. TEST_COVERAGE_PLAN.md)
- [ ] Тест выполняется быстро (unit <10ms, integration <200ms)
- [ ] Нет warning'ов от PHPUnit
- [ ] Нет deprecation notice
- [ ] Mutation score >75% (если используется Infection)

## 🚨 Red Flags

Следующие вещи **недопустимы** в тестах:

❌ `sleep()` в unit тестах
❌ Реальные HTTP запросы
❌ Зависимость от внешних сервисов
❌ Hardcoded пути к файлам
❌ Тесты зависящие от даты/времени без мока
❌ Shared state между тестами
❌ Порядок выполнения тестов имеет значение
❌ Random данные без seed
❌ Ignored/Skipped тесты без объяснения
❌ TODO комментарии в committed коде
❌ Закомментированный код
❌ Debug выводы (var_dump, echo, etc.)

## 📝 Финальная проверка перед commit

- [ ] Все тесты проходят локально (`vendor/bin/phpunit`)
- [ ] Нет failing тестов
- [ ] Нет skipped тестов (или есть объяснение в issue)
- [ ] PHPStan без ошибок (`vendor/bin/phpstan analyze`)
- [ ] Code style соблюден (`vendor/bin/php-cs-fixer fix`)
- [ ] Добавлены/обновлены комментарии где необходимо
- [ ] README обновлен если добавлена новая функциональность

---

## Пример идеального теста

```php
<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Tests\Context;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pulse\Flags\Core\Context\UserContext;

final class UserContextTest extends TestCase
{
    #[Test]
    public function it_can_be_created_with_minimal_parameters(): void
    {
        // Arrange
        $userId = 'user-123';

        // Act
        $context = new UserContext(userId: $userId);

        // Assert
        self::assertInstanceOf(UserContext::class, $context);
        self::assertSame($userId, $context->getUserId());
        self::assertNull($context->getSessionId());
        self::assertNull($context->getCompanyId());
    }

    #[Test]
    #[DataProvider('provideContextConfigurations')]
    public function it_converts_to_array_correctly(
        string $userId,
        ?string $sessionId,
        ?string $companyId,
        array $expectedArray
    ): void {
        // Arrange
        $context = new UserContext(
            userId: $userId,
            sessionId: $sessionId,
            companyId: $companyId
        );

        // Act
        $result = $context->toArray();

        // Assert
        self::assertSame($expectedArray, $result);
    }

    public static function provideContextConfigurations(): iterable
    {
        yield 'minimal context with only user id' => [
            'userId' => 'user-123',
            'sessionId' => null,
            'companyId' => null,
            'expectedArray' => [
                'user_id' => 'user-123',
            ],
        ];

        yield 'full context with all fields' => [
            'userId' => 'user-456',
            'sessionId' => 'session-789',
            'companyId' => 'company-012',
            'expectedArray' => [
                'user_id' => 'user-456',
                'session_id' => 'session-789',
                'company_id' => 'company-012',
            ],
        ];
    }
}
```

---

**Версия**: 1.0
**Последнее обновление**: 2025-12-22
