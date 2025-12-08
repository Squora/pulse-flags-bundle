<?php

declare(strict_types=1);

namespace Pulse\FlagsBundle\Tests\Unit\Command\Setup;

use PHPUnit\Framework\TestCase;
use Pulse\FlagsBundle\Command\Setup\InitStorageCommand;
use Pulse\FlagsBundle\Storage\DbStorage;
use Pulse\FlagsBundle\Storage\YamlStorage;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Unit tests for InitStorageCommand class.
 *
 * Tests console command for initializing persistent storage including:
 * - Option configuration (--force)
 * - DbStorage initialization with --force flag
 * - DbStorage initialization with confirmation prompt
 * - Cancelled initialization (user says no)
 * - Error handling during initialization
 * - Skips initialization for non-DbStorage backends (YAML, PHP, etc.)
 * - Success/warning/error message display
 */
class InitStorageCommandTest extends TestCase
{
    public function testItHasCorrectConfiguration(): void
    {
        // Arrange: Create command with mock storage
        $storage = $this->createMock(DbStorage::class);
        $command = new InitStorageCommand($storage);

        // Act: Get command definition
        $definition = $command->getDefinition();

        // Assert: Has force option
        $this->assertTrue($definition->hasOption('force'));
        $this->assertFalse($definition->getOption('force')->acceptValue());
    }

    public function testItInitializesDbStorageWithForceFlag(): void
    {
        // Arrange: Mock DbStorage
        $storage = $this->createMock(DbStorage::class);
        $storage->method('getDriver')->willReturn('mysql');
        $storage->expects($this->once())->method('initializeTable');

        $command = new InitStorageCommand($storage);
        $commandTester = new CommandTester($command);

        // Act: Execute with --force
        $exitCode = $commandTester->execute(['--force' => true]);

        // Assert: Success
        $this->assertEquals(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('initialized successfully', $output);
        $this->assertStringContainsString('pulse_feature_flags', $output);
        $this->assertStringContainsString('mysql', $output);
    }

    public function testItInitializesDbStorageWithShortForceOption(): void
    {
        // Arrange: Mock DbStorage
        $storage = $this->createMock(DbStorage::class);
        $storage->method('getDriver')->willReturn('pgsql');
        $storage->expects($this->once())->method('initializeTable');

        $command = new InitStorageCommand($storage);
        $commandTester = new CommandTester($command);

        // Act: Execute with -f (short option)
        $exitCode = $commandTester->execute(['-f' => true]);

        // Assert: Success
        $this->assertEquals(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('initialized successfully', $output);
    }

    public function testItAsksForConfirmationWithoutForceFlag(): void
    {
        // Arrange: Mock DbStorage
        $storage = $this->createMock(DbStorage::class);
        $storage->method('getDriver')->willReturn('mysql');
        $storage->expects($this->once())->method('initializeTable');

        $command = new InitStorageCommand($storage);
        $commandTester = new CommandTester($command);

        // Act: Execute with confirmation input (yes)
        $commandTester->setInputs(['yes']);
        $exitCode = $commandTester->execute([]);

        // Assert: Success after confirmation
        $this->assertEquals(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Continue?', $output);
        $this->assertStringContainsString('initialized successfully', $output);
    }

    public function testItCancelsInitializationWhenUserDeclinesConfirmation(): void
    {
        // Arrange: Mock DbStorage
        $storage = $this->createMock(DbStorage::class);
        $storage->expects($this->never())->method('initializeTable');

        $command = new InitStorageCommand($storage);
        $commandTester = new CommandTester($command);

        // Act: Execute with 'no' confirmation
        $commandTester->setInputs(['no']);
        $exitCode = $commandTester->execute([]);

        // Assert: Success (but cancelled)
        $this->assertEquals(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('cancelled', $output);
    }

    public function testItReturnsFailureOnDbStorageError(): void
    {
        // Arrange: Mock DbStorage that throws exception
        $storage = $this->createMock(DbStorage::class);
        $storage->method('initializeTable')
            ->willThrowException(new \Exception('Database connection failed'));

        $command = new InitStorageCommand($storage);
        $commandTester = new CommandTester($command);

        // Act: Execute with --force
        $exitCode = $commandTester->execute(['--force' => true]);

        // Assert: Failure
        $this->assertEquals(Command::FAILURE, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Failed to initialize', $output);
        $this->assertStringContainsString('Database connection failed', $output);
    }

    public function testItSkipsInitializationForNonDbStorage(): void
    {
        // Arrange: Mock YamlStorage (not DbStorage)
        $storage = $this->createMock(YamlStorage::class);

        $command = new InitStorageCommand($storage);
        $commandTester = new CommandTester($command);

        // Act: Execute
        $exitCode = $commandTester->execute([]);

        // Assert: Success (but skipped)
        $this->assertEquals(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('does not require initialization', $output);
        $this->assertStringContainsString('DbStorage', $output);
        $this->assertStringContainsString('YamlStorage', $output);
    }

    public function testItSkipsInitializationForNonDbStorageEvenWithForce(): void
    {
        // Arrange: Mock YamlStorage
        $storage = $this->createMock(YamlStorage::class);

        $command = new InitStorageCommand($storage);
        $commandTester = new CommandTester($command);

        // Act: Execute with --force (should still skip)
        $exitCode = $commandTester->execute(['--force' => true]);

        // Assert: Success (skipped, --force has no effect)
        $this->assertEquals(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('does not require initialization', $output);
    }
}
