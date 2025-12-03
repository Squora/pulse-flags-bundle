<?php

declare(strict_types=1);

namespace Pulse\FlagsBundle\Tests\Unit\Command;

use PHPUnit\Framework\TestCase;
use Pulse\FlagsBundle\Command\CreateFlagCommand;
use Pulse\FlagsBundle\Service\PersistentFeatureFlagService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Unit tests for CreateFlagCommand class.
 *
 * Tests console command for creating persistent feature flags including:
 * - Argument configuration (name, enabled)
 * - Creating disabled flag (default behavior)
 * - Creating enabled flag (with '1', 'true', etc.)
 * - Boolean conversion for enabled argument
 * - Returns FAILURE when flag already exists
 * - Calls configure() with correct parameters
 * - Success/error message display
 * - Only works with persistent flags (not permanent)
 */
class CreateFlagCommandTest extends TestCase
{
    private PersistentFeatureFlagService $flagService;
    private CreateFlagCommand $command;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        // Arrange: Create service mock
        $this->flagService = $this->createMock(PersistentFeatureFlagService::class);

        // Create command with mocked service
        $this->command = new CreateFlagCommand($this->flagService);

        // Create command tester for input/output testing
        $this->commandTester = new CommandTester($this->command);
    }

    public function testItHasCorrectConfiguration(): void
    {
        // Arrange: Command created in setUp

        // Act: Get command definition
        $definition = $this->command->getDefinition();

        // Assert: Has name argument (required)
        $this->assertTrue($definition->hasArgument('name'));
        $this->assertTrue($definition->getArgument('name')->isRequired());

        // Assert: Has enabled argument (optional)
        $this->assertTrue($definition->hasArgument('enabled'));
        $this->assertFalse($definition->getArgument('enabled')->isRequired());
        $this->assertEquals('0', $definition->getArgument('enabled')->getDefault());
    }

    public function testItCreatesDisabledFlagByDefault(): void
    {
        // Arrange: Flag doesn't exist
        $this->flagService->method('exists')->with('new.flag')->willReturn(false);
        $this->flagService->expects($this->once())
            ->method('configure')
            ->with('new.flag', ['enabled' => false]);

        // Act: Execute without enabled argument
        $exitCode = $this->commandTester->execute(['name' => 'new.flag']);

        // Assert: Success
        $this->assertEquals(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('created', $output);
        $this->assertStringContainsString('Disabled', $output);
    }

    public function testItCreatesEnabledFlagWithOne(): void
    {
        // Arrange: Flag doesn't exist
        $this->flagService->method('exists')->with('enabled.flag')->willReturn(false);
        $this->flagService->expects($this->once())
            ->method('configure')
            ->with('enabled.flag', ['enabled' => true]);

        // Act: Execute with enabled='1'
        $exitCode = $this->commandTester->execute([
            'name' => 'enabled.flag',
            'enabled' => '1',
        ]);

        // Assert: Success
        $this->assertEquals(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('created', $output);
        $this->assertStringContainsString('Enabled', $output);
    }

    public function testItCreatesEnabledFlagWithTrueString(): void
    {
        // Arrange: Flag doesn't exist
        $this->flagService->method('exists')->with('test.flag')->willReturn(false);
        $this->flagService->expects($this->once())
            ->method('configure')
            ->with('test.flag', ['enabled' => true]);

        // Act: Execute with enabled='true'
        $exitCode = $this->commandTester->execute([
            'name' => 'test.flag',
            'enabled' => 'true',
        ]);

        // Assert: Success and enabled
        $this->assertEquals(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Enabled', $output);
    }

    public function testItCreatesDisabledFlagWithZero(): void
    {
        // Arrange: Flag doesn't exist
        $this->flagService->method('exists')->with('disabled.flag')->willReturn(false);
        $this->flagService->expects($this->once())
            ->method('configure')
            ->with('disabled.flag', ['enabled' => false]);

        // Act: Execute with enabled='0'
        $exitCode = $this->commandTester->execute([
            'name' => 'disabled.flag',
            'enabled' => '0',
        ]);

        // Assert: Success and disabled
        $this->assertEquals(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Disabled', $output);
    }

    public function testItCreatesDisabledFlagWithFalseString(): void
    {
        // Arrange: Flag doesn't exist
        $this->flagService->method('exists')->with('test.flag')->willReturn(false);
        $this->flagService->expects($this->once())
            ->method('configure')
            ->with('test.flag', ['enabled' => false]);

        // Act: Execute with enabled='false'
        $exitCode = $this->commandTester->execute([
            'name' => 'test.flag',
            'enabled' => 'false',
        ]);

        // Assert: Success and disabled
        $this->assertEquals(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Disabled', $output);
    }

    public function testItReturnsFailureWhenFlagAlreadyExists(): void
    {
        // Arrange: Flag already exists
        $this->flagService->method('exists')->with('existing.flag')->willReturn(true);

        // configure() should NOT be called
        $this->flagService->expects($this->never())->method('configure');

        // Act: Execute
        $exitCode = $this->commandTester->execute(['name' => 'existing.flag']);

        // Assert: Failure
        $this->assertEquals(Command::FAILURE, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('already exists', $output);
        $this->assertStringContainsString('existing.flag', $output);
    }

    public function testItShowsSuccessMessageWithFlagName(): void
    {
        // Arrange: Flag doesn't exist
        $this->flagService->method('exists')->with('my.feature')->willReturn(false);
        $this->flagService->method('configure');

        // Act: Execute
        $exitCode = $this->commandTester->execute([
            'name' => 'my.feature',
            'enabled' => '1',
        ]);

        // Assert: Success message contains flag name and status
        $this->assertEquals(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('my.feature', $output);
        $this->assertStringContainsString('created', $output);
        $this->assertStringContainsString('Enabled', $output);
    }
}
