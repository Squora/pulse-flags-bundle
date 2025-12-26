# Pulse Flags Bundle - Test Suite

Комплексный набор тестов для Pulse Flags Bundle.

## 📚 Документация

- **[TEST_COVERAGE_PLAN.md](TEST_COVERAGE_PLAN.md)** - Детальный план покрытия всего бандла тестами
- **[COVERAGE_SUMMARY.md](COVERAGE_SUMMARY.md)** - Краткая сводка текущего статуса покрытия
- **[TEST_CHECKLIST.md](TEST_CHECKLIST.md)** - Чек-лист для написания и review тестов
- **[TEMPLATE.php](TEMPLATE.php)** - Шаблон для новых тестов

## 🎯 Цели покрытия

- **Core компоненты** (Context, Strategy, Operators): 100%
- **Бизнес-логика** (Services, Storage, Validation): 90-95%
- **Вспомогательные компоненты** (Commands, DI, Twig): 80%+
- **Общее покрытие проекта**: 85%+

## 🏗️ Структура тестов

```
tests/
├── README.md                      # Этот файл
├── TEST_COVERAGE_PLAN.md          # Детальный план покрытия
├── COVERAGE_SUMMARY.md            # Краткая сводка
├── TEST_CHECKLIST.md              # Чек-лист для review
├── TEMPLATE.php                   # Шаблон теста
│
├── Context/                       # Value Objects тесты
│   ├── README.md
│   ├── CompositeContextTest.php   ✅ (18 тестов)
│   ├── UserContextTest.php        ⏳
│   ├── GeoContextTest.php         ⏳
│   └── ...
│
├── Strategy/                      # Стратегии тесты
│   ├── PercentageStrategyTest.php ⏳
│   ├── UserIdStrategyTest.php     ⏳
│   ├── CompositeStrategyTest.php  ⏳
│   │
│   ├── Operator/                  # Операторы
│   │   ├── EqualsOperatorTest.php ⏳
│   │   └── ...
│   │
│   ├── Validation/                # Валидаторы
│   │   ├── ValidationServiceTest.php ⏳
│   │   └── ...
│   │
│   └── Hash/                      # Хеширование
│       └── HashCalculatorTest.php ⏳
│
├── Storage/                       # Storage тесты
│   ├── YamlStorageTest.php        ⏳
│   ├── PhpStorageTest.php         ⏳
│   └── DbStorageTest.php          ⏳
│
├── Service/                       # Сервисы тесты
│   ├── AbstractFeatureFlagServiceServiceTest.php ⏳
│   ├── PersistentFeatureFlagServiceTest.php      ⏳
│   └── PermanentFeatureFlagServiceTest.php       ⏳
│
├── Segment/                       # Сегменты тесты
│   ├── StaticSegmentTest.php      ⏳
│   ├── DynamicSegmentTest.php     ⏳
│   └── SegmentRepositoryTest.php  ⏳
│
├── Command/                       # CLI команды тесты
│   ├── Flag/
│   ├── Query/
│   ├── Segment/
│   └── Setup/
│
├── DependencyInjection/           # DI тесты
│   ├── PulseFlagsExtensionTest.php       ⏳
│   ├── ConfigurationTest.php             ⏳
│   └── FlagsConfigurationLoaderTest.php  ⏳
│
└── Twig/                          # Twig расширение тесты
    └── FeatureFlagExtensionTest.php ⏳
```

## 🚀 Быстрый старт

### Запуск всех тестов

```bash
vendor/bin/phpunit
```

### Запуск конкретной группы тестов

```bash
# Только Context тесты
vendor/bin/phpunit tests/Context/

# Только один класс
vendor/bin/phpunit tests/Context/CompositeContextTest.php

# С подробным выводом
vendor/bin/phpunit --testdox

# С coverage (требует Xdebug)
XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-html var/coverage
```

### Запуск с фильтром

```bash
# Только тесты содержащие "merge" в имени
vendor/bin/phpunit --filter merge

# Только конкретный тест
vendor/bin/phpunit --filter it_merges_all_contexts_to_array
```

## 📝 Написание нового теста

### 1. Используйте шаблон

Скопируйте `TEMPLATE.php` как основу для нового теста:

```bash
cp tests/TEMPLATE.php tests/Context/NewContextTest.php
```

### 2. Следуйте AAA структуре

```php
#[Test]
public function it_does_something_when_condition(): void
{
    // Arrange - подготовка
    $dependency = $this->createMock(Interface::class);

    // Act - действие
    $result = $sut->doSomething();

    // Assert - проверка
    self::assertSame($expected, $result);
}
```

### 3. Используйте Data Providers

Для параметризованных тестов:

```php
#[Test]
#[DataProvider('provideTestCases')]
public function it_handles_various_inputs($input, $expected): void
{
    // ...
}

public static function provideTestCases(): iterable
{
    yield 'descriptive name' => ['input' => ..., 'expected' => ...];
}
```

### 4. Проверьте по чек-листу

Перед commit проверьте свой тест по [TEST_CHECKLIST.md](TEST_CHECKLIST.md).

## 🎨 Best Practices

### ✅ DO

- **Используйте AAA структуру** в каждом тесте
- **Именуйте тесты описательно**: `it_should_*`, `it_returns_*`, `it_throws_*`
- **Используйте `assertSame()`** вместо `assertEquals()` для strict comparison
- **Тестируйте edge cases**: null, пустые значения, границы
- **Изолируйте тесты**: каждый тест независим
- **Используйте моки** для зависимостей в unit тестах
- **Data providers** для 3+ похожих сценариев
- **Именованные параметры** для читаемости

### ❌ DON'T

- **Не используйте sleep()** в unit тестах
- **Не делайте реальные HTTP запросы**
- **Не зависьте от внешних сервисов**
- **Не используйте hardcoded пути**
- **Не создавайте зависимости между тестами**
- **Не коммитьте failing/skipped тесты** без объяснения
- **Не оставляйте debug код** (var_dump, echo, etc.)

## 🔍 Типы тестов

### Unit тесты
- Тестируют один класс изолированно
- Все зависимости замокированы
- Быстрые (<10ms)
- Нет I/O операций

### Integration тесты
- Тестируют взаимодействие компонентов
- Используют реальные зависимости
- Могут быть медленнее (<200ms)
- Помечены соответствующей группой

### Functional тесты
- End-to-end тестирование
- Полный flow
- Реальное окружение

## 📊 Текущий статус

| Модуль | Классов | Покрыто | % |
|--------|---------|---------|---|
| Context | 9 | 1 | 11% |
| Strategy | 10 | 0 | 0% |
| Operator | 14 | 0 | 0% |
| Validation | 13 | 0 | 0% |
| Storage | 3 | 0 | 0% |
| Service | 3 | 0 | 0% |
| Segment | 3 | 0 | 0% |
| Command | 10 | 0 | 0% |
| DI | 3 | 0 | 0% |
| Twig | 1 | 0 | 0% |
| Other | 14 | 0 | 0% |
| **ИТОГО** | **83** | **1** | **1.2%** |

**Всего тестов**: 18
**Всего assertions**: 26

## 🎯 Ближайшие задачи

### Эта неделя
- [ ] UserContextTest
- [ ] GeoContextTest
- [ ] EmptyContextTest
- [ ] DateRangeContextTest

### Следующая неделя
- [ ] All Operator tests (14 классов)
- [ ] HashCalculatorTest
- [ ] PercentageStrategyTest

## 🛠️ Инструменты

### PHPUnit
```bash
vendor/bin/phpunit                    # Запустить все тесты
vendor/bin/phpunit --testdox          # Подробный вывод
vendor/bin/phpunit --coverage-text    # Coverage в консоли
vendor/bin/phpunit --coverage-html var/coverage  # HTML отчет
```

### PHPStan
```bash
vendor/bin/phpstan analyze            # Статический анализ
vendor/bin/phpstan analyze tests/     # Анализ тестов
```

### PHP CS Fixer
```bash
vendor/bin/php-cs-fixer fix           # Исправить code style
vendor/bin/php-cs-fixer fix --dry-run # Проверка без изменений
```

## 📖 Полезные ссылки

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [PHPUnit Best Practices](https://phpunit.de/manual/current/en/writing-tests-for-phpunit.html)
- [Mocking with PHPUnit](https://phpunit.de/manual/current/en/test-doubles.html)
- [Data Providers](https://phpunit.de/manual/current/en/writing-tests-for-phpunit.html#writing-tests-for-phpunit.data-providers)

## 🤝 Contributing

1. Выберите класс из [TEST_COVERAGE_PLAN.md](TEST_COVERAGE_PLAN.md)
2. Создайте тест используя [TEMPLATE.php](TEMPLATE.php)
3. Следуйте [TEST_CHECKLIST.md](TEST_CHECKLIST.md)
4. Запустите тесты локально
5. Создайте Pull Request

## 📝 Примеры

### Пример хорошего теста

```php
#[Test]
#[DataProvider('providePercentageValues')]
public function it_validates_percentage_range(
    int $percentage,
    bool $expectedValid
): void {
    // Arrange
    $validator = new PercentageValidator();

    // Act
    $result = $validator->isValid($percentage);

    // Assert
    self::assertSame($expectedValid, $result);
}

public static function providePercentageValues(): iterable
{
    yield 'zero is valid' => [0, true];
    yield 'fifty is valid' => [50, true];
    yield 'hundred is valid' => [100, true];
    yield 'negative is invalid' => [-1, false];
    yield 'over hundred is invalid' => [101, false];
}
```

### Пример с моками

```php
#[Test]
public function it_retrieves_flag_from_storage_when_checking(): void
{
    // Arrange
    $storage = $this->createMock(StorageInterface::class);
    $storage->expects(self::once())
        ->method('get')
        ->with(self::equalTo('feature-x'))
        ->willReturn(['status' => 'enabled']);

    $service = new FeatureFlagService($storage);

    // Act
    $result = $service->isEnabled('feature-x');

    // Assert
    self::assertTrue($result);
}
```

---

**Версия**: 1.0
**Последнее обновление**: 2025-12-22
**Maintainer**: Pulse Flags Bundle Team
