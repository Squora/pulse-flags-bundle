<?php

declare(strict_types=1);

namespace Pulse\FlagsBundle\Tests\Unit\Command\Flag;

use PHPUnit\Framework\TestCase;
use Pulse\FlagsBundle\Command\Flag\RemoveFlagCommand;
use Pulse\FlagsBundle\Service\PersistentFeatureFlagService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Unit tests for RemoveFlagCommand class.
 *
 * Tests console command for removing persistent feature flags including:
 * - Argument configuration (name)
 * - Removing existing flag successfully
 * - Returns FAILURE when flag doesn't exist
 * - Calls service remove() method
 * - Success/error message display
 * - Only works with persistent flags (not permanent)
 */
class RemoveFlagCommandTest extends TestCase
{
    private PersistentFeatureFlagService $flagService;
    private RemoveFlagCommand $command;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        // Arrange: Create service mock
        $this->flagService = $this->createMock(PersistentFeatureFlagService::class);

        // Create command with mocked service
        $this->command = new RemoveFlagCommand($this->flagService);

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
    }

    public function testItRemovesExistingFlag(): void
    {
        // Arrange: Flag exists
        $this->flagService->method('exists')->with('test.flag')->willReturn(true);
        $this->flagService->expects($this->once())
            ->method('remove')
            ->with('test.flag');

        // Act: Execute
        $exitCode = $this->commandTester->execute(['name' => 'test.flag']);

        // Assert: Success
        $this->assertEquals(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('removed', $output);
        $this->assertStringContainsString('test.flag', $output);
    }

    public function testItReturnsFailureWhenFlagDoesNotExist(): void
    {
        // Arrange: Flag doesn't exist
        $this->flagService->method('exists')->with('nonexistent.flag')->willReturn(false);

        // remove() should NOT be called
        $this->flagService->expects($this->never())->method('remove');

        // Act: Execute
        $exitCode = $this->commandTester->execute(['name' => 'nonexistent.flag']);

        // Assert: Failure
        $this->assertEquals(Command::FAILURE, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('does not exist', $output);
        $this->assertStringContainsString('nonexistent.flag', $output);
    }

    public function testItShowsSuccessMessageWithFlagName(): void
    {
        // Arrange: Flag exists
        $this->flagService->method('exists')->with('my.feature')->willReturn(true);
        $this->flagService->method('remove');

        // Act: Execute
        $exitCode = $this->commandTester->execute(['name' => 'my.feature']);

        // Assert: Success message contains flag name
        $this->assertEquals(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('my.feature', $output);
        $this->assertStringContainsString('removed permanently', $output);
    }

    public function testItRemovesFlagWithDotNotation(): void
    {
        // Arrange: Flag with dot notation exists
        $this->flagService->method('exists')->with('core.auth.sso')->willReturn(true);
        $this->flagService->expects($this->once())
            ->method('remove')
            ->with('core.auth.sso');

        // Act: Execute
        $exitCode = $this->commandTester->execute(['name' => 'core.auth.sso']);

        // Assert: Success
        $this->assertEquals(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('core.auth.sso', $output);
    }

    public function testItRemovesFlagWithUnderscoreNotation(): void
    {
        // Arrange: Flag with underscore notation exists
        $this->flagService->method('exists')->with('my_feature_flag')->willReturn(true);
        $this->flagService->expects($this->once())
            ->method('remove')
            ->with('my_feature_flag');

        // Act: Execute
        $exitCode = $this->commandTester->execute(['name' => 'my_feature_flag']);

        // Assert: Success
        $this->assertEquals(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('my_feature_flag', $output);
    }
}
