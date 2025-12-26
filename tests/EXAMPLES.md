# Test Examples - Practical Patterns

Коллекция практических примеров тестирования для Pulse Flags Bundle.

## 📑 Содержание

1. [Context Tests](#context-tests)
2. [Strategy Tests](#strategy-tests)
3. [Operator Tests](#operator-tests)
4. [Validator Tests](#validator-tests)
5. [Storage Tests](#storage-tests)
6. [Service Tests](#service-tests)
7. [Statistical Tests](#statistical-tests)
8. [Command Tests](#command-tests)

---

## Context Tests

### Простой Value Object

```php
<?php

namespace Pulse\Flags\Core\Tests\Context;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pulse\Flags\Core\Context\UserContext;

final class UserContextTest extends TestCase
{
    #[Test]
    public function it_can_be_created_with_user_id_only(): void
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
    public function it_can_be_created_with_all_parameters(): void
    {
        // Arrange
        $userId = 'user-123';
        $sessionId = 'session-456';
        $companyId = 'company-789';

        // Act
        $context = new UserContext(
            userId: $userId,
            sessionId: $sessionId,
            companyId: $companyId
        );

        // Assert
        self::assertSame($userId, $context->getUserId());
        self::assertSame($sessionId, $context->getSessionId());
        self::assertSame($companyId, $context->getCompanyId());
    }

    #[Test]
    #[DataProvider('provideToArrayScenarios')]
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

    public static function provideToArrayScenarios(): iterable
    {
        yield 'only user id' => [
            'userId' => 'user-1',
            'sessionId' => null,
            'companyId' => null,
            'expectedArray' => ['user_id' => 'user-1'],
        ];

        yield 'user id and session id' => [
            'userId' => 'user-2',
            'sessionId' => 'session-2',
            'companyId' => null,
            'expectedArray' => [
                'user_id' => 'user-2',
                'session_id' => 'session-2',
            ],
        ];

        yield 'all fields' => [
            'userId' => 'user-3',
            'sessionId' => 'session-3',
            'companyId' => 'company-3',
            'expectedArray' => [
                'user_id' => 'user-3',
                'session_id' => 'session-3',
                'company_id' => 'company-3',
            ],
        ];
    }

    #[Test]
    public function it_handles_special_characters_in_values(): void
    {
        // Arrange
        $userId = 'user-!@#$%';
        $sessionId = 'session-<script>';
        $companyId = 'company-"quotes"';

        // Act
        $context = new UserContext(
            userId: $userId,
            sessionId: $sessionId,
            companyId: $companyId
        );

        // Assert
        self::assertSame($userId, $context->getUserId());
        self::assertSame($sessionId, $context->getSessionId());
        self::assertSame($companyId, $context->getCompanyId());
    }
}
```

---

## Strategy Tests

### Percentage Strategy (с статистикой)

```php
<?php

namespace Pulse\Flags\Core\Tests\Strategy;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pulse\Flags\Core\Strategy\PercentageStrategy;
use Pulse\Flags\Core\Strategy\Hash\HashCalculator;

final class PercentageStrategyTest extends TestCase
{
    #[Test]
    public function it_returns_strategy_name(): void
    {
        // Arrange
        $hashCalculator = new HashCalculator();
        $strategy = new PercentageStrategy($hashCalculator);

        // Act
        $name = $strategy->getName();

        // Assert
        self::assertSame('percentage', $name);
    }

    #[Test]
    #[DataProvider('providePercentageConfigs')]
    public function it_evaluates_percentage_correctly(
        int $percentage,
        string $userId,
        bool $expectedEnabled
    ): void {
        // Arrange
        $hashCalculator = new HashCalculator();
        $strategy = new PercentageStrategy($hashCalculator);
        $config = ['percentage' => $percentage];
        $context = ['user_id' => $userId];

        // Act
        $result = $strategy->isEnabled($config, $context);

        // Assert
        self::assertSame($expectedEnabled, $result);
    }

    public static function providePercentageConfigs(): iterable
    {
        yield '0 percent should always be disabled' => [
            'percentage' => 0,
            'userId' => 'any-user',
            'expectedEnabled' => false,
        ];

        yield '100 percent should always be enabled' => [
            'percentage' => 100,
            'userId' => 'any-user',
            'expectedEnabled' => true,
        ];
    }

    #[Test]
    public function it_produces_deterministic_results(): void
    {
        // Arrange
        $hashCalculator = new HashCalculator();
        $strategy = new PercentageStrategy($hashCalculator);
        $config = ['percentage' => 50];
        $context = ['user_id' => 'test-user'];

        // Act - call multiple times
        $result1 = $strategy->isEnabled($config, $context);
        $result2 = $strategy->isEnabled($config, $context);
        $result3 = $strategy->isEnabled($config, $context);

        // Assert - should always return the same result
        self::assertSame($result1, $result2);
        self::assertSame($result2, $result3);
    }

    /**
     * Statistical test: verifies that distribution is approximately correct.
     * With 10,000 users and 30% rollout, we expect ~3,000 enabled (±5%).
     */
    #[Test]
    public function it_distributes_users_approximately_according_to_percentage(): void
    {
        // Arrange
        $hashCalculator = new HashCalculator();
        $strategy = new PercentageStrategy($hashCalculator);
        $targetPercentage = 30;
        $config = ['percentage' => $targetPercentage];
        $totalUsers = 10000;
        $tolerance = 5; // ±5% tolerance

        // Act - test with many users
        $enabledCount = 0;
        for ($i = 0; $i < $totalUsers; $i++) {
            $context = ['user_id' => "user-{$i}"];
            if ($strategy->isEnabled($config, $context)) {
                $enabledCount++;
            }
        }

        // Assert
        $actualPercentage = ($enabledCount / $totalUsers) * 100;
        $lowerBound = $targetPercentage - $tolerance;
        $upperBound = $targetPercentage + $tolerance;

        self::assertGreaterThanOrEqual(
            $lowerBound,
            $actualPercentage,
            "Expected at least {$lowerBound}%, got {$actualPercentage}%"
        );
        self::assertLessThanOrEqual(
            $upperBound,
            $actualPercentage,
            "Expected at most {$upperBound}%, got {$actualPercentage}%"
        );
    }

    #[Test]
    public function it_throws_exception_when_user_id_is_missing(): void
    {
        // Arrange
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('user_id is required');

        $hashCalculator = new HashCalculator();
        $strategy = new PercentageStrategy($hashCalculator);
        $config = ['percentage' => 50];
        $context = []; // Missing user_id

        // Act
        $strategy->isEnabled($config, $context);
    }
}
```

### Composite Strategy

```php
<?php

namespace Pulse\Flags\Core\Tests\Strategy;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pulse\Flags\Core\Strategy\CompositeStrategy;
use Pulse\Flags\Core\Strategy\StrategyInterface;

final class CompositeStrategyTest extends TestCase
{
    #[Test]
    public function it_returns_true_when_all_strategies_return_true_in_and_mode(): void
    {
        // Arrange
        $strategy1 = $this->createMock(StrategyInterface::class);
        $strategy1->method('isEnabled')->willReturn(true);

        $strategy2 = $this->createMock(StrategyInterface::class);
        $strategy2->method('isEnabled')->willReturn(true);

        $composite = new CompositeStrategy(
            strategies: [$strategy1, $strategy2],
            mode: 'AND'
        );

        $config = ['strategies' => [/* ... */]];
        $context = [];

        // Act
        $result = $composite->isEnabled($config, $context);

        // Assert
        self::assertTrue($result);
    }

    #[Test]
    public function it_returns_false_when_any_strategy_returns_false_in_and_mode(): void
    {
        // Arrange
        $strategy1 = $this->createMock(StrategyInterface::class);
        $strategy1->method('isEnabled')->willReturn(true);

        $strategy2 = $this->createMock(StrategyInterface::class);
        $strategy2->method('isEnabled')->willReturn(false);

        $composite = new CompositeStrategy(
            strategies: [$strategy1, $strategy2],
            mode: 'AND'
        );

        $config = [];
        $context = [];

        // Act
        $result = $composite->isEnabled($config, $context);

        // Assert
        self::assertFalse($result);
    }

    #[Test]
    public function it_returns_true_when_any_strategy_returns_true_in_or_mode(): void
    {
        // Arrange
        $strategy1 = $this->createMock(StrategyInterface::class);
        $strategy1->method('isEnabled')->willReturn(false);

        $strategy2 = $this->createMock(StrategyInterface::class);
        $strategy2->method('isEnabled')->willReturn(true);

        $composite = new CompositeStrategy(
            strategies: [$strategy1, $strategy2],
            mode: 'OR'
        );

        $config = [];
        $context = [];

        // Act
        $result = $composite->isEnabled($config, $context);

        // Assert
        self::assertTrue($result);
    }

    #[Test]
    public function it_short_circuits_in_and_mode_when_first_returns_false(): void
    {
        // Arrange
        $strategy1 = $this->createMock(StrategyInterface::class);
        $strategy1->expects(self::once())
            ->method('isEnabled')
            ->willReturn(false);

        $strategy2 = $this->createMock(StrategyInterface::class);
        $strategy2->expects(self::never()) // Should not be called
            ->method('isEnabled');

        $composite = new CompositeStrategy(
            strategies: [$strategy1, $strategy2],
            mode: 'AND'
        );

        // Act
        $result = $composite->isEnabled([], []);

        // Assert
        self::assertFalse($result);
    }
}
```

---

## Operator Tests

### String Comparison Operator

```php
<?php

namespace Pulse\Flags\Core\Tests\Strategy\Operator;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pulse\Flags\Core\Strategy\Operator\ContainsOperator;

final class ContainsOperatorTest extends TestCase
{
    #[Test]
    #[DataProvider('provideContainsScenarios')]
    public function it_checks_if_string_contains_substring(
        mixed $value,
        mixed $compareWith,
        bool $expected,
        string $description
    ): void {
        // Arrange
        $operator = new ContainsOperator();

        // Act
        $result = $operator->evaluate($value, $compareWith);

        // Assert
        self::assertSame($expected, $result, $description);
    }

    public static function provideContainsScenarios(): iterable
    {
        yield 'string contains substring' => [
            'value' => 'hello world',
            'compareWith' => 'world',
            'expected' => true,
            'description' => 'Should find substring',
        ];

        yield 'string does not contain substring' => [
            'value' => 'hello world',
            'compareWith' => 'foo',
            'expected' => false,
            'description' => 'Should not find missing substring',
        ];

        yield 'case sensitive - different case' => [
            'value' => 'Hello World',
            'compareWith' => 'world',
            'expected' => false,
            'description' => 'Should be case sensitive',
        ];

        yield 'empty string contains empty string' => [
            'value' => '',
            'compareWith' => '',
            'expected' => true,
            'description' => 'Empty contains empty',
        ];

        yield 'any string contains empty string' => [
            'value' => 'test',
            'compareWith' => '',
            'expected' => true,
            'description' => 'Any string contains empty',
        ];

        yield 'unicode string contains unicode substring' => [
            'value' => 'Привет мир',
            'compareWith' => 'мир',
            'expected' => true,
            'description' => 'Should handle unicode',
        ];

        yield 'special characters' => [
            'value' => 'test@example.com',
            'compareWith' => '@example',
            'expected' => true,
            'description' => 'Should handle special chars',
        ];

        yield 'numeric values as strings' => [
            'value' => '12345',
            'compareWith' => '234',
            'expected' => true,
            'description' => 'Should handle numeric strings',
        ];
    }

    #[Test]
    public function it_returns_operator_name(): void
    {
        // Arrange
        $operator = new ContainsOperator();

        // Act
        $name = $operator->getName();

        // Assert
        self::assertSame('contains', $name);
    }
}
```

### Regex Operator

```php
<?php

namespace Pulse\Flags\Core\Tests\Strategy\Operator;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pulse\Flags\Core\Strategy\Operator\RegexOperator;

final class RegexOperatorTest extends TestCase
{
    #[Test]
    #[DataProvider('provideRegexScenarios')]
    public function it_matches_against_regex_pattern(
        string $value,
        string $pattern,
        bool $expected
    ): void {
        // Arrange
        $operator = new RegexOperator();

        // Act
        $result = $operator->evaluate($value, $pattern);

        // Assert
        self::assertSame($expected, $result);
    }

    public static function provideRegexScenarios(): iterable
    {
        yield 'email pattern matches valid email' => [
            'value' => 'test@example.com',
            'pattern' => '/^[\w\-\.]+@[\w\-\.]+\.\w+$/',
            'expected' => true,
        ];

        yield 'email pattern does not match invalid email' => [
            'value' => 'invalid-email',
            'pattern' => '/^[\w\-\.]+@[\w\-\.]+\.\w+$/',
            'expected' => false,
        ];

        yield 'digits only pattern' => [
            'value' => '12345',
            'pattern' => '/^\d+$/',
            'expected' => true,
        ];

        yield 'phone number format' => [
            'value' => '+7-123-456-7890',
            'pattern' => '/^\+\d{1,3}-\d{3}-\d{3}-\d{4}$/',
            'expected' => true,
        ];

        yield 'case insensitive flag' => [
            'value' => 'TEST',
            'pattern' => '/test/i',
            'expected' => true,
        ];
    }

    #[Test]
    public function it_throws_exception_for_invalid_regex_pattern(): void
    {
        // Arrange
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid regex pattern');

        $operator = new RegexOperator();

        // Act
        $operator->evaluate('test', 'invalid[regex');
    }
}
```

---

## Validator Tests

### Configuration Validator

```php
<?php

namespace Pulse\Flags\Core\Tests\Strategy\Validation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pulse\Flags\Core\Strategy\Validation\PercentageStrategyValidator;
use Pulse\Flags\Core\Strategy\Validation\ValidationResult;

final class PercentageStrategyValidatorTest extends TestCase
{
    #[Test]
    #[DataProvider('provideValidConfigurations')]
    public function it_accepts_valid_configurations(array $config): void
    {
        // Arrange
        $validator = new PercentageStrategyValidator();

        // Act
        $result = $validator->validate($config);

        // Assert
        self::assertTrue($result->isValid());
        self::assertEmpty($result->getErrors());
    }

    public static function provideValidConfigurations(): iterable
    {
        yield 'zero percent' => [['percentage' => 0]];
        yield 'fifty percent' => [['percentage' => 50]];
        yield 'hundred percent' => [['percentage' => 100]];
    }

    #[Test]
    #[DataProvider('provideInvalidConfigurations')]
    public function it_rejects_invalid_configurations(
        array $config,
        string $expectedError
    ): void {
        // Arrange
        $validator = new PercentageStrategyValidator();

        // Act
        $result = $validator->validate($config);

        // Assert
        self::assertFalse($result->isValid());
        self::assertContains($expectedError, $result->getErrors());
    }

    public static function provideInvalidConfigurations(): iterable
    {
        yield 'missing percentage field' => [
            'config' => [],
            'expectedError' => 'percentage field is required',
        ];

        yield 'negative percentage' => [
            'config' => ['percentage' => -1],
            'expectedError' => 'percentage must be between 0 and 100',
        ];

        yield 'percentage over 100' => [
            'config' => ['percentage' => 101],
            'expectedError' => 'percentage must be between 0 and 100',
        ];

        yield 'percentage is not integer' => [
            'config' => ['percentage' => 'fifty'],
            'expectedError' => 'percentage must be an integer',
        ];

        yield 'percentage is float' => [
            'config' => ['percentage' => 50.5],
            'expectedError' => 'percentage must be an integer',
        ];
    }
}
```

---

## Storage Tests

### File-based Storage

```php
<?php

namespace Pulse\Flags\Core\Tests\Storage;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pulse\Flags\Core\Storage\YamlStorage;

final class YamlStorageTest extends TestCase
{
    private string $tempDir;
    private YamlStorage $storage;

    protected function setUp(): void
    {
        parent::setUp();

        // Create temporary directory for test files
        $this->tempDir = sys_get_temp_dir() . '/pulse_flags_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);

        $this->storage = new YamlStorage($this->tempDir . '/flags.yaml');
    }

    protected function tearDown(): void
    {
        // Cleanup: remove all test files
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->tempDir);
        }

        parent::tearDown();
    }

    #[Test]
    public function it_can_store_and_retrieve_flag(): void
    {
        // Arrange
        $flagName = 'test-flag';
        $flagConfig = [
            'status' => 'enabled',
            'strategy' => 'simple',
        ];

        // Act
        $this->storage->set($flagName, $flagConfig);
        $retrieved = $this->storage->get($flagName);

        // Assert
        self::assertSame($flagConfig, $retrieved);
    }

    #[Test]
    public function it_returns_null_for_non_existent_flag(): void
    {
        // Arrange
        $flagName = 'non-existent';

        // Act
        $result = $this->storage->get($flagName);

        // Assert
        self::assertNull($result);
    }

    #[Test]
    public function it_can_check_if_flag_exists(): void
    {
        // Arrange
        $flagName = 'test-flag';
        $flagConfig = ['status' => 'enabled'];

        // Act & Assert - before
        self::assertFalse($this->storage->has($flagName));

        // Act - store
        $this->storage->set($flagName, $flagConfig);

        // Assert - after
        self::assertTrue($this->storage->has($flagName));
    }

    #[Test]
    public function it_can_remove_flag(): void
    {
        // Arrange
        $flagName = 'test-flag';
        $this->storage->set($flagName, ['status' => 'enabled']);

        // Act
        $this->storage->remove($flagName);

        // Assert
        self::assertFalse($this->storage->has($flagName));
        self::assertNull($this->storage->get($flagName));
    }

    #[Test]
    public function it_can_retrieve_all_flags(): void
    {
        // Arrange
        $flags = [
            'flag-1' => ['status' => 'enabled'],
            'flag-2' => ['status' => 'disabled'],
            'flag-3' => ['status' => 'enabled'],
        ];

        foreach ($flags as $name => $config) {
            $this->storage->set($name, $config);
        }

        // Act
        $allFlags = $this->storage->all();

        // Assert
        self::assertSame($flags, $allFlags);
    }

    #[Test]
    public function it_can_clear_all_flags(): void
    {
        // Arrange
        $this->storage->set('flag-1', ['status' => 'enabled']);
        $this->storage->set('flag-2', ['status' => 'enabled']);

        // Act
        $this->storage->clear();

        // Assert
        self::assertEmpty($this->storage->all());
    }

    #[Test]
    public function it_can_paginate_flags(): void
    {
        // Arrange - create 25 flags
        for ($i = 1; $i <= 25; $i++) {
            $this->storage->set("flag-{$i}", ['status' => 'enabled']);
        }

        // Act
        $page1 = $this->storage->paginate(page: 1, limit: 10);
        $page2 = $this->storage->paginate(page: 2, limit: 10);
        $page3 = $this->storage->paginate(page: 3, limit: 10);

        // Assert
        self::assertCount(10, $page1['flags']);
        self::assertCount(10, $page2['flags']);
        self::assertCount(5, $page3['flags']);
        self::assertSame(25, $page1['pagination']['total']);
        self::assertSame(3, $page1['pagination']['pages']);
    }

    #[Test]
    public function it_handles_concurrent_writes_safely(): void
    {
        // This test verifies file locking mechanism
        // Arrange
        $flag1 = ['status' => 'enabled', 'id' => 1];
        $flag2 = ['status' => 'enabled', 'id' => 2];

        // Act - simulate concurrent writes
        $this->storage->set('flag-1', $flag1);
        $this->storage->set('flag-2', $flag2);

        // Assert - both should be stored
        self::assertSame($flag1, $this->storage->get('flag-1'));
        self::assertSame($flag2, $this->storage->get('flag-2'));
    }
}
```

---

## Statistical Tests

### Hash Distribution Test

```php
<?php

namespace Pulse\Flags\Core\Tests\Strategy\Hash;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pulse\Flags\Core\Strategy\Hash\HashCalculator;

final class HashCalculatorTest extends TestCase
{
    /**
     * Statistical test for uniform distribution.
     *
     * This test verifies that hash values are uniformly distributed
     * across the 0-100 range using chi-square goodness-of-fit test.
     */
    #[Test]
    public function it_produces_uniform_distribution_across_buckets(): void
    {
        // Arrange
        $calculator = new HashCalculator();
        $totalSamples = 10000;
        $bucketCount = 10; // 0-9, 10-19, ..., 90-99
        $buckets = array_fill(0, $bucketCount, 0);
        $expectedPerBucket = $totalSamples / $bucketCount;

        // Act - generate hashes for many users
        for ($i = 0; $i < $totalSamples; $i++) {
            $hash = $calculator->calculate("user-{$i}");
            $bucketIndex = (int)floor($hash / 10);
            $buckets[$bucketIndex]++;
        }

        // Assert - chi-square test
        $chiSquare = 0;
        foreach ($buckets as $observed) {
            $chiSquare += pow($observed - $expectedPerBucket, 2) / $expectedPerBucket;
        }

        // Critical value for chi-square with 9 degrees of freedom at 95% confidence
        $criticalValue = 16.919;

        self::assertLessThan(
            $criticalValue,
            $chiSquare,
            sprintf(
                'Hash distribution failed chi-square test. χ² = %.2f (critical = %.2f). Buckets: %s',
                $chiSquare,
                $criticalValue,
                json_encode($buckets)
            )
        );
    }

    #[Test]
    public function it_produces_deterministic_hashes(): void
    {
        // Arrange
        $calculator = new HashCalculator();
        $input = 'test-user-123';

        // Act - calculate hash multiple times
        $hash1 = $calculator->calculate($input);
        $hash2 = $calculator->calculate($input);
        $hash3 = $calculator->calculate($input);

        // Assert - all should be identical
        self::assertSame($hash1, $hash2);
        self::assertSame($hash2, $hash3);
    }

    #[Test]
    public function it_produces_different_hashes_for_different_inputs(): void
    {
        // Arrange
        $calculator = new HashCalculator();

        // Act
        $hashes = [];
        for ($i = 0; $i < 100; $i++) {
            $hashes[] = $calculator->calculate("user-{$i}");
        }

        // Assert - all hashes should be unique
        $uniqueHashes = array_unique($hashes);
        self::assertCount(100, $uniqueHashes, 'All hashes should be unique');
    }
}
```

---

## Command Tests

### Console Command Test

```php
<?php

namespace Pulse\Flags\Core\Tests\Command\Flag;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pulse\Flags\Core\Command\Flag\CreateFlagCommand;
use Pulse\Flags\Core\Service\FeatureFlagServiceInterface;
use Symfony\Component\Console\Tester\CommandTester;

final class CreateFlagCommandTest extends TestCase
{
    #[Test]
    public function it_creates_flag_with_valid_parameters(): void
    {
        // Arrange
        $service = $this->createMock(FeatureFlagServiceInterface::class);
        $service->expects(self::once())
            ->method('createFlag')
            ->with(
                self::equalTo('new-feature'),
                self::equalTo(['strategy' => 'simple', 'status' => 'enabled'])
            );

        $command = new CreateFlagCommand($service);
        $tester = new CommandTester($command);

        // Act
        $exitCode = $tester->execute([
            'name' => 'new-feature',
            '--strategy' => 'simple',
            '--status' => 'enabled',
        ]);

        // Assert
        self::assertSame(0, $exitCode, 'Command should succeed');
        self::assertStringContainsString(
            'Flag "new-feature" created successfully',
            $tester->getDisplay()
        );
    }

    #[Test]
    public function it_shows_error_when_flag_already_exists(): void
    {
        // Arrange
        $service = $this->createMock(FeatureFlagServiceInterface::class);
        $service->method('createFlag')
            ->willThrowException(new \RuntimeException('Flag already exists'));

        $command = new CreateFlagCommand($service);
        $tester = new CommandTester($command);

        // Act
        $exitCode = $tester->execute([
            'name' => 'existing-flag',
        ]);

        // Assert
        self::assertSame(1, $exitCode, 'Command should fail');
        self::assertStringContainsString(
            'Flag already exists',
            $tester->getDisplay()
        );
    }
}
```

---

**Версия**: 1.0
**Последнее обновление**: 2025-12-22
