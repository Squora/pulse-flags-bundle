<?php

declare(strict_types=1);

namespace Pulse\FlagsBundle\Tests\Unit\Command;

use PHPUnit\Framework\TestCase;
use Pulse\FlagsBundle\Command\CheckFlagCommand;
use Pulse\FlagsBundle\Service\PermanentFeatureFlagService;
use Pulse\FlagsBundle\Service\PersistentFeatureFlagService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Unit tests for CheckFlagCommand class.
 *
 * Tests console command for checking feature flag status including:
 * - Argument and option configuration (name, --user-id, --session-id)
 * - Permanent flag checking with enabled/disabled status
 * - Persistent flag checking with enabled/disabled status
 * - Flag not found returns FAILURE
 * - Context propagation (user_id, session_id)
 * - Output formatting (success/error messages, configuration table, context table)
 * - Permanent flag priority over persistent flags
 */
class CheckFlagCommandTest extends TestCase
{
    private PermanentFeatureFlagService $permanentService;
    private PersistentFeatureFlagService $persistentService;
    private CheckFlagCommand $command;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        // Arrange: Create service mocks
        $this->permanentService = $this->createMock(PermanentFeatureFlagService::class);
        $this->persistentService = $this->createMock(PersistentFeatureFlagService::class);

        // Create command with mocked services
        $this->command = new CheckFlagCommand(
            $this->permanentService,
            $this->persistentService
        );

        // Create command tester for input/output testing
        $this->commandTester = new CommandTester($this->command);
    }

    public function testItHasCorrectConfiguration(): void
    {
        // Arrange: Command created in setUp

        // Act: Get command definition
        $definition = $this->command->getDefinition();

        // Assert: Has name argument
        $this->assertTrue($definition->hasArgument('name'));
        $this->assertTrue($definition->getArgument('name')->isRequired());

        // Assert: Has user-id option
        $this->assertTrue($definition->hasOption('user-id'));
        $this->assertTrue($definition->getOption('user-id')->acceptValue());

        // Assert: Has session-id option
        $this->assertTrue($definition->hasOption('session-id'));
        $this->assertTrue($definition->getOption('session-id')->acceptValue());
    }

    public function testItShowsSuccessForEnabledPermanentFlag(): void
    {
        // Arrange: Permanent flag exists and is enabled
        $config = ['enabled' => true, 'strategy' => 'simple'];
        $this->permanentService->method('getConfig')->with('test.flag')->willReturn($config);
        $this->permanentService->method('isEnabled')->with('test.flag', [])->willReturn(true);

        // Act: Execute command
        $exitCode = $this->commandTester->execute(['name' => 'test.flag']);

        // Assert: Success exit code
        $this->assertEquals(Command::SUCCESS, $exitCode);

        // Assert: Output contains success message
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('ENABLED', $output);
        $this->assertStringContainsString('test.flag', $output);
        $this->assertStringContainsString('Permanent', $output);
    }

    public function testItShowsErrorForDisabledPermanentFlag(): void
    {
        // Arrange: Permanent flag exists but is disabled
        $config = ['enabled' => false, 'strategy' => 'simple'];
        $this->permanentService->method('getConfig')->with('disabled.flag')->willReturn($config);
        $this->permanentService->method('isEnabled')->with('disabled.flag', [])->willReturn(false);

        // Act: Execute command
        $exitCode = $this->commandTester->execute(['name' => 'disabled.flag']);

        // Assert: Success exit code (command ran successfully)
        $this->assertEquals(Command::SUCCESS, $exitCode);

        // Assert: Output shows disabled status
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('DISABLED', $output);
    }

    public function testItChecksPersistentFlagWhenPermanentNotFound(): void
    {
        // Arrange: Permanent flag doesn't exist, persistent does
        $config = ['enabled' => true, 'strategy' => 'percentage', 'percentage' => 50];
        $this->permanentService->method('getConfig')->with('persistent.flag')->willReturn(null);
        $this->persistentService->method('getConfig')->with('persistent.flag')->willReturn($config);
        $this->persistentService->method('isEnabled')->with('persistent.flag', [])->willReturn(true);

        // Act: Execute command
        $exitCode = $this->commandTester->execute(['name' => 'persistent.flag']);

        // Assert: Success and shows persistent type
        $this->assertEquals(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Persistent', $output);
        $this->assertStringContainsString('ENABLED', $output);
    }

    public function testItReturnsFailureWhenFlagNotFound(): void
    {
        // Arrange: Flag doesn't exist in either service
        $this->permanentService->method('getConfig')->with('nonexistent')->willReturn(null);
        $this->persistentService->method('getConfig')->with('nonexistent')->willReturn(null);

        // Act: Execute command
        $exitCode = $this->commandTester->execute(['name' => 'nonexistent']);

        // Assert: Failure exit code
        $this->assertEquals(Command::FAILURE, $exitCode);

        // Assert: Output shows warning
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('not configured', $output);
        $this->assertStringContainsString('nonexistent', $output);
    }

    public function testItPropagatesUserIdContext(): void
    {
        // Arrange: Flag with percentage strategy
        $config = ['enabled' => true, 'strategy' => 'percentage', 'percentage' => 25];
        $context = ['user_id' => '123'];

        $this->permanentService->method('getConfig')->willReturn(null);
        $this->persistentService->method('getConfig')->willReturn($config);
        $this->persistentService->expects($this->once())
            ->method('isEnabled')
            ->with('test.flag', $context)
            ->willReturn(true);

        // Act: Execute with --user-id option
        $exitCode = $this->commandTester->execute([
            'name' => 'test.flag',
            '--user-id' => '123',
        ]);

        // Assert: Success and context displayed
        $this->assertEquals(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Context:', $output);
        $this->assertStringContainsString('user_id', $output);
        $this->assertStringContainsString('123', $output);
    }

    public function testItPropagatesSessionIdContext(): void
    {
        // Arrange: Flag with context
        $config = ['enabled' => true, 'strategy' => 'simple'];
        $context = ['session_id' => 'abc123'];

        $this->permanentService->method('getConfig')->willReturn(null);
        $this->persistentService->method('getConfig')->willReturn($config);
        $this->persistentService->expects($this->once())
            ->method('isEnabled')
            ->with('test.flag', $context)
            ->willReturn(true);

        // Act: Execute with --session-id option
        $exitCode = $this->commandTester->execute([
            'name' => 'test.flag',
            '--session-id' => 'abc123',
        ]);

        // Assert: Success and context displayed
        $this->assertEquals(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('session_id', $output);
        $this->assertStringContainsString('abc123', $output);
    }

    public function testItPropagatesBothContextOptions(): void
    {
        // Arrange: Flag with both context values
        $config = ['enabled' => true, 'strategy' => 'percentage', 'percentage' => 50];
        $context = ['user_id' => '456', 'session_id' => 'xyz789'];

        $this->permanentService->method('getConfig')->willReturn(null);
        $this->persistentService->method('getConfig')->willReturn($config);
        $this->persistentService->expects($this->once())
            ->method('isEnabled')
            ->with('test.flag', $context)
            ->willReturn(false);

        // Act: Execute with both options
        $exitCode = $this->commandTester->execute([
            'name' => 'test.flag',
            '--user-id' => '456',
            '--session-id' => 'xyz789',
        ]);

        // Assert: Both context values displayed
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('user_id', $output);
        $this->assertStringContainsString('456', $output);
        $this->assertStringContainsString('session_id', $output);
        $this->assertStringContainsString('xyz789', $output);
    }

    public function testItDisplaysConfigurationTable(): void
    {
        // Arrange: Flag with full configuration
        $config = [
            'enabled' => true,
            'strategy' => 'user_id',
            'whitelist' => ['1', '2', '3'],
            'blacklist' => ['4', '5'],
        ];
        $this->permanentService->method('getConfig')->willReturn($config);
        $this->permanentService->method('isEnabled')->willReturn(true);

        // Act: Execute command
        $this->commandTester->execute(['name' => 'test.flag']);

        // Assert: Configuration table displayed
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Configuration:', $output);
        $this->assertStringContainsString('Type', $output);
        $this->assertStringContainsString('Strategy', $output);
        $this->assertStringContainsString('user_id', $output);
        $this->assertStringContainsString('Whitelist', $output);
        $this->assertStringContainsString('1, 2, 3', $output);
        $this->assertStringContainsString('Blacklist', $output);
        $this->assertStringContainsString('4, 5', $output);
    }

    public function testItDisplaysPercentageInConfiguration(): void
    {
        // Arrange: Flag with percentage strategy
        $config = [
            'enabled' => true,
            'strategy' => 'percentage',
            'percentage' => 75,
        ];
        $this->permanentService->method('getConfig')->willReturn(null);
        $this->persistentService->method('getConfig')->willReturn($config);
        $this->persistentService->method('isEnabled')->willReturn(true);

        // Act: Execute command
        $this->commandTester->execute(['name' => 'test.flag']);

        // Assert: Percentage displayed in configuration
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Percentage', $output);
        $this->assertStringContainsString('75', $output);
        $this->assertStringContainsString('percentage', $output);
    }

    public function testItDoesNotShowContextTableWhenNoContextProvided(): void
    {
        // Arrange: Flag without context
        $config = ['enabled' => true, 'strategy' => 'simple'];
        $this->permanentService->method('getConfig')->willReturn($config);
        $this->permanentService->method('isEnabled')->willReturn(true);

        // Act: Execute without context options
        $this->commandTester->execute(['name' => 'test.flag']);

        // Assert: Context table should not be present
        $output = $this->commandTester->getDisplay();
        // Context section should not appear when no context provided
        // We can verify by checking the number of "Configuration:" sections
        $this->assertStringContainsString('Configuration:', $output);
        $this->assertStringNotContainsString('Context:', $output);
    }
}
