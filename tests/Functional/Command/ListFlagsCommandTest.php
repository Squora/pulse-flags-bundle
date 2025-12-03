<?php

declare(strict_types=1);

namespace Pulse\FlagsBundle\Tests\Functional\Command;

use PHPUnit\Framework\TestCase;
use Pulse\FlagsBundle\Command\ListFlagsCommand;
use Pulse\FlagsBundle\Service\PermanentFeatureFlagService;
use Pulse\FlagsBundle\Service\PersistentFeatureFlagService;
use Pulse\FlagsBundle\Storage\PhpStorage;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class ListFlagsCommandTest extends TestCase
{
    private CommandTester $commandTester;
    private PermanentFeatureFlagService $permanentFlagService;
    private PersistentFeatureFlagService $persistentFlagService;

    protected function setUp(): void
    {
        // Setup permanent flags
        $permanentFlags = [
            'core.permanent' => ['enabled' => true, 'strategy' => 'simple', 'description' => 'Permanent flag'],
        ];
        $this->permanentFlagService = new PermanentFeatureFlagService($permanentFlags);

        // Setup persistent flags
        $storage = new PhpStorage(); // In-memory mode
        $this->persistentFlagService = new PersistentFeatureFlagService($storage);

        $command = new ListFlagsCommand($this->permanentFlagService, $this->persistentFlagService);

        $application = new Application();
        $application->add($command);

        $this->commandTester = new CommandTester($command);
    }

    public function testExecuteWithOnlyPermanentFlags(): void
    {
        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('core.permanent', $output);
        $this->assertStringContainsString('Permanent', $output);
        $this->assertStringContainsString('1 permanent, 0 persistent', $output);
        $this->assertEquals(0, $this->commandTester->getStatusCode());
    }

    public function testExecuteWithPersistentFlags(): void
    {
        $this->persistentFlagService->configure('test.flag1', [
            'enabled' => true,
            'strategy' => 'simple',
            'description' => 'Test flag 1',
        ]);

        $this->persistentFlagService->configure('test.flag2', [
            'enabled' => false,
            'strategy' => 'percentage',
            'percentage' => 25,
            'description' => 'Test flag 2',
        ]);

        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();

        $this->assertStringContainsString('test.flag1', $output);
        $this->assertStringContainsString('test.flag2', $output);
        $this->assertStringContainsString('Test flag 1', $output);
        $this->assertStringContainsString('Test flag 2', $output);
        $this->assertStringContainsString('simple', $output);
        $this->assertStringContainsString('percentage', $output);
        $this->assertStringContainsString('Enabled', $output);
        $this->assertStringContainsString('Disabled', $output);
        $this->assertStringContainsString('Persistent', $output);
        $this->assertStringContainsString('1 permanent, 2 persistent', $output);
        $this->assertEquals(0, $this->commandTester->getStatusCode());
    }

    public function testExecuteShowsPercentage(): void
    {
        $this->persistentFlagService->configure('test.percent', [
            'enabled' => true,
            'strategy' => 'percentage',
            'percentage' => 50,
        ]);

        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();

        $this->assertStringContainsString('test.percent', $output);
        $this->assertStringContainsString('50', $output);
        $this->assertEquals(0, $this->commandTester->getStatusCode());
    }
}
