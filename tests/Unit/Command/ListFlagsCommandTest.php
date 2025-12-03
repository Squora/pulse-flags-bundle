<?php

declare(strict_types=1);

namespace Pulse\FlagsBundle\Tests\Unit\Command;

use PHPUnit\Framework\TestCase;
use Pulse\FlagsBundle\Command\ListFlagsCommand;
use Pulse\FlagsBundle\Service\PermanentFeatureFlagService;
use Pulse\FlagsBundle\Service\PersistentFeatureFlagService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Unit tests for ListFlagsCommand class.
 *
 * Tests console command for listing all feature flags including:
 * - Displaying permanent and persistent flags
 * - Table formatting with correct columns
 * - Handling empty flag list
 * - Pagination through multiple pages
 * - Correct flag type labels (Permanent/Persistent)
 * - Enabled/Disabled status display
 * - Strategy information display
 * - Strategy details (percentage, dates, etc.)
 */
class ListFlagsCommandTest extends TestCase
{
    private PermanentFeatureFlagService $permanentFlagService;
    private PersistentFeatureFlagService $persistentFlagService;
    private ListFlagsCommand $command;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        // Arrange: Create service mocks
        $this->permanentFlagService = $this->createMock(PermanentFeatureFlagService::class);
        $this->persistentFlagService = $this->createMock(PersistentFeatureFlagService::class);

        // Create command with mocked services
        $this->command = new ListFlagsCommand(
            $this->permanentFlagService,
            $this->persistentFlagService
        );

        // Create command tester for input/output testing
        $this->commandTester = new CommandTester($this->command);
    }

    public function testItDisplaysNoFlagsMessage(): void
    {
        // Arrange: No flags in either service
        $this->permanentFlagService->method('paginate')->willReturn([
            'flags' => [],
            'pagination' => ['pages' => 1, 'current' => 1, 'total' => 0],
        ]);
        $this->persistentFlagService->method('paginate')->willReturn([
            'flags' => [],
            'pagination' => ['pages' => 1, 'current' => 1, 'total' => 0],
        ]);

        // Act: Execute
        $exitCode = $this->commandTester->execute([]);

        // Assert: Success with info message
        $this->assertEquals(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('No feature flags', $output);
    }

    public function testItDisplaysPermanentFlagsOnly(): void
    {
        // Arrange: Only permanent flags exist
        $this->permanentFlagService->method('paginate')->willReturn([
            'flags' => [
                'core.auth' => [
                    'enabled' => true,
                    'strategy' => 'simple',
                    'description' => 'Authentication feature',
                ],
            ],
            'pagination' => ['pages' => 1, 'current' => 1, 'total' => 1],
        ]);
        $this->persistentFlagService->method('paginate')->willReturn([
            'flags' => [],
            'pagination' => ['pages' => 1, 'current' => 1, 'total' => 0],
        ]);

        // Act: Execute
        $exitCode = $this->commandTester->execute([]);

        // Assert: Success with table display
        $this->assertEquals(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('core.auth', $output);
        $this->assertStringContainsString('Authentication feature', $output);
        $this->assertStringContainsString('Enabled', $output);
        $this->assertStringContainsString('Permanent', $output);
        $this->assertStringContainsString('1 permanent, 0 persistent', $output);
    }

    public function testItDisplaysPersistentFlagsOnly(): void
    {
        // Arrange: Only persistent flags exist
        $this->permanentFlagService->method('paginate')->willReturn([
            'flags' => [],
            'pagination' => ['pages' => 1, 'current' => 1, 'total' => 0],
        ]);
        $this->persistentFlagService->method('paginate')->willReturn([
            'flags' => [
                'api.v2' => [
                    'enabled' => false,
                    'strategy' => 'percentage',
                    'percentage' => 25,
                    'description' => 'API v2 rollout',
                ],
            ],
            'pagination' => ['pages' => 1, 'current' => 1, 'total' => 1],
        ]);

        // Act: Execute
        $exitCode = $this->commandTester->execute([]);

        // Assert: Success with table display
        $this->assertEquals(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('api.v2', $output);
        $this->assertStringContainsString('API v2 rollout', $output);
        $this->assertStringContainsString('Disabled', $output);
        $this->assertStringContainsString('Persistent', $output);
        $this->assertStringContainsString('0 permanent, 1 persistent', $output);
    }

    public function testItDisplaysBothPermanentAndPersistentFlags(): void
    {
        // Arrange: Both types of flags exist
        $this->permanentFlagService->method('paginate')->willReturn([
            'flags' => [
                'core.feature' => [
                    'enabled' => true,
                    'strategy' => 'simple',
                    'description' => 'Core feature',
                ],
            ],
            'pagination' => ['pages' => 1, 'current' => 1, 'total' => 1],
        ]);
        $this->persistentFlagService->method('paginate')->willReturn([
            'flags' => [
                'experimental.feature' => [
                    'enabled' => true,
                    'strategy' => 'simple',
                    'description' => 'Experimental feature',
                ],
            ],
            'pagination' => ['pages' => 1, 'current' => 1, 'total' => 1],
        ]);

        // Act: Execute
        $exitCode = $this->commandTester->execute([]);

        // Assert: Success with both flags displayed
        $this->assertEquals(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('core.feature', $output);
        $this->assertStringContainsString('experimental.feature', $output);
        $this->assertStringContainsString('Found 2 feature flag(s)', $output);
        $this->assertStringContainsString('1 permanent, 1 persistent', $output);
    }

    public function testItDisplaysPercentageStrategyDetails(): void
    {
        // Arrange: Flag with percentage strategy
        $this->permanentFlagService->method('paginate')->willReturn([
            'flags' => [],
            'pagination' => ['pages' => 1, 'current' => 1, 'total' => 0],
        ]);
        $this->persistentFlagService->method('paginate')->willReturn([
            'flags' => [
                'test.feature' => [
                    'enabled' => true,
                    'strategy' => 'percentage',
                    'percentage' => 50,
                    'description' => 'Test feature',
                ],
            ],
            'pagination' => ['pages' => 1, 'current' => 1, 'total' => 1],
        ]);

        // Act: Execute
        $exitCode = $this->commandTester->execute([]);

        // Assert: Percentage details shown
        $this->assertEquals(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('test.feature', $output);
        $this->assertStringContainsString('percentage', $output);
        $this->assertStringContainsString('50', $output);
    }

    public function testItDisplaysDateRangeStrategyDetails(): void
    {
        // Arrange: Flag with date_range strategy
        $this->permanentFlagService->method('paginate')->willReturn([
            'flags' => [
                'holiday.promo' => [
                    'enabled' => true,
                    'strategy' => 'date_range',
                    'start_date' => '2024-12-01',
                    'end_date' => '2024-12-31',
                    'description' => 'Holiday promotion',
                ],
            ],
            'pagination' => ['pages' => 1, 'current' => 1, 'total' => 1],
        ]);
        $this->persistentFlagService->method('paginate')->willReturn([
            'flags' => [],
            'pagination' => ['pages' => 1, 'current' => 1, 'total' => 0],
        ]);

        // Act: Execute
        $exitCode = $this->commandTester->execute([]);

        // Assert: Date range details shown
        $this->assertEquals(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('holiday.promo', $output);
        $this->assertStringContainsString('2024-12-01', $output);
        $this->assertStringContainsString('2024-12-31', $output);
    }

    public function testItHandlesPaginationCorrectly(): void
    {
        // Arrange: Multiple pages of flags
        $this->permanentFlagService->expects($this->exactly(2))
            ->method('paginate')
            ->willReturnOnConsecutiveCalls(
                [
                    'flags' => ['flag1' => ['enabled' => true, 'strategy' => 'simple']],
                    'pagination' => ['pages' => 2, 'current' => 1, 'total' => 2],
                ],
                [
                    'flags' => ['flag2' => ['enabled' => false, 'strategy' => 'simple']],
                    'pagination' => ['pages' => 2, 'current' => 2, 'total' => 2],
                ]
            );
        $this->persistentFlagService->method('paginate')->willReturn([
            'flags' => [],
            'pagination' => ['pages' => 1, 'current' => 1, 'total' => 0],
        ]);

        // Act: Execute
        $exitCode = $this->commandTester->execute([]);

        // Assert: Both flags displayed
        $this->assertEquals(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('flag1', $output);
        $this->assertStringContainsString('flag2', $output);
    }

    public function testItDisplaysTableHeaders(): void
    {
        // Arrange: One flag exists
        $this->permanentFlagService->method('paginate')->willReturn([
            'flags' => [
                'test.flag' => [
                    'enabled' => true,
                    'strategy' => 'simple',
                ],
            ],
            'pagination' => ['pages' => 1, 'current' => 1, 'total' => 1],
        ]);
        $this->persistentFlagService->method('paginate')->willReturn([
            'flags' => [],
            'pagination' => ['pages' => 1, 'current' => 1, 'total' => 0],
        ]);

        // Act: Execute
        $exitCode = $this->commandTester->execute([]);

        // Assert: Table headers present
        $this->assertEquals(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Flag Name', $output);
        $this->assertStringContainsString('Description', $output);
        $this->assertStringContainsString('Status', $output);
        $this->assertStringContainsString('Type', $output);
        $this->assertStringContainsString('Strategy', $output);
        $this->assertStringContainsString('Details', $output);
    }

    public function testItHandlesFlagWithoutDescription(): void
    {
        // Arrange: Flag without description
        $this->permanentFlagService->method('paginate')->willReturn([
            'flags' => [
                'test.flag' => [
                    'enabled' => true,
                    'strategy' => 'simple',
                ],
            ],
            'pagination' => ['pages' => 1, 'current' => 1, 'total' => 1],
        ]);
        $this->persistentFlagService->method('paginate')->willReturn([
            'flags' => [],
            'pagination' => ['pages' => 1, 'current' => 1, 'total' => 0],
        ]);

        // Act: Execute
        $exitCode = $this->commandTester->execute([]);

        // Assert: Success and description shown as dash
        $this->assertEquals(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('test.flag', $output);
    }
}
