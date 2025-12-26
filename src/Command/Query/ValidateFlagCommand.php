<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Command\Query;

use Pulse\Flags\Core\Service\FeatureFlagServiceInterface;
use Pulse\Flags\Core\Strategy\Validation\ValidationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command to validate feature flag configuration.
 *
 * Checks flag configuration for errors and potential issues.
 */
#[AsCommand(
    name: 'pulse:flags:validate',
    description: 'Validate feature flag configuration'
)]
class ValidateFlagCommand extends Command
{
    public function __construct(
        private readonly FeatureFlagServiceInterface $flagService,
        private readonly ValidationService $validationService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('flag', InputArgument::OPTIONAL, 'Flag name to validate (if omitted, validates all flags)')
            ->setHelp(<<<'HELP'
The <info>%command.name%</info> command validates feature flag configurations.

<info>Validate single flag:</info>
  <info>php %command.full_name% experiments.new_feature</info>

<info>Validate all flags:</info>
  <info>php %command.full_name%</info>

The command checks for:
- Required fields
- Value ranges and formats
- Date/time validity
- Strategy-specific rules
- Potential performance issues

Exit codes:
  0 - All flags valid
  1 - Validation errors found
  2 - Warnings found (non-blocking)
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $flagName = $input->getArgument('flag');

        if ($flagName) {
            return $this->validateSingleFlag($flagName, $io);
        }

        return $this->validateAllFlags($io);
    }

    /**
     * Validate a single flag.
     */
    private function validateSingleFlag(string $flagName, SymfonyStyle $io): int
    {
        $io->title('Validating Flag: ' . $flagName);

        // Check if flag exists
        if (!$this->flagService->exists($flagName)) {
            $io->error(sprintf('Flag "%s" not found', $flagName));
            return Command::FAILURE;
        }

        // Get configuration
        $config = $this->flagService->getConfig($flagName);
        if (!$config) {
            $io->error(sprintf('Failed to load configuration for flag "%s"', $flagName));
            return Command::FAILURE;
        }

        // Validate
        $result = $this->validationService->validate($config);

        // Display results
        if ($result->isValid()) {
            $io->success('Flag configuration is valid');

            if ($result->hasWarnings()) {
                $io->warning('Warnings:');
                foreach ($result->getWarnings() as $warning) {
                    $io->writeln('  • ' . $warning);
                }
                return 2; // Exit code 2 for warnings
            }

            return Command::SUCCESS;
        }

        // Has errors
        $io->error('Flag configuration has errors:');
        foreach ($result->getErrors() as $error) {
            $io->writeln('  • ' . $error);
        }

        if ($result->hasWarnings()) {
            $io->warning('Warnings:');
            foreach ($result->getWarnings() as $warning) {
                $io->writeln('  • ' . $warning);
            }
        }

        return Command::FAILURE;
    }

    /**
     * Validate all flags.
     */
    private function validateAllFlags(SymfonyStyle $io): int
    {
        $io->title('Validating All Flags');

        $allFlags = $this->flagService->all();

        if (empty($allFlags)) {
            $io->warning('No flags found');
            return Command::SUCCESS;
        }

        $totalFlags = count($allFlags);
        $validFlags = 0;
        $flagsWithWarnings = 0;
        $flagsWithErrors = 0;
        $allErrors = [];
        $allWarnings = [];

        $io->progressStart($totalFlags);

        foreach ($allFlags as $flagName => $config) {
            $result = $this->validationService->validate($config);

            if ($result->isValid()) {
                $validFlags++;
                if ($result->hasWarnings()) {
                    $flagsWithWarnings++;
                    $allWarnings[$flagName] = $result->getWarnings();
                }
            } else {
                $flagsWithErrors++;
                $allErrors[$flagName] = $result->getErrors();
                if ($result->hasWarnings()) {
                    $allWarnings[$flagName] = $result->getWarnings();
                }
            }

            $io->progressAdvance();
        }

        $io->progressFinish();

        // Summary
        $io->newLine();
        $io->section('Validation Summary');

        $io->table(
            ['Metric', 'Count'],
            [
                ['Total flags', $totalFlags],
                ['Valid flags', $validFlags],
                ['Flags with warnings', $flagsWithWarnings],
                ['Flags with errors', $flagsWithErrors],
            ]
        );

        // Display errors
        if (!empty($allErrors)) {
            $io->error(sprintf('Found errors in %d flag(s):', $flagsWithErrors));

            foreach ($allErrors as $flagName => $errors) {
                $io->writeln(sprintf('<error>%s:</error>', $flagName));
                foreach ($errors as $error) {
                    $io->writeln(sprintf('  • %s', $error));
                }
                $io->newLine();
            }
        }

        // Display warnings
        if (!empty($allWarnings)) {
            $io->warning(sprintf('Found warnings in %d flag(s):', count($allWarnings)));

            foreach ($allWarnings as $flagName => $warnings) {
                $io->writeln(sprintf('<comment>%s:</comment>', $flagName));
                foreach ($warnings as $warning) {
                    $io->writeln(sprintf('  • %s', $warning));
                }
                $io->newLine();
            }
        }

        // Final status
        if ($flagsWithErrors > 0) {
            $io->error('Validation failed');
            return Command::FAILURE;
        }

        if ($flagsWithWarnings > 0) {
            $io->success('Validation passed with warnings');
            return 2; // Exit code 2 for warnings
        }

        $io->success('All flags are valid');
        return Command::SUCCESS;
    }
}
