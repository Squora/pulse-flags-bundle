<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Command\Query;

use Pulse\Flags\Core\Service\FeatureFlagServiceInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command to test feature flag evaluation with specific context.
 *
 * Useful for debugging and testing flag configurations.
 */
#[AsCommand(
    name: 'pulse:flags:test',
    description: 'Test feature flag evaluation with context'
)]
class TestFlagCommand extends Command
{
    public function __construct(
        private readonly FeatureFlagServiceInterface $flagService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('flag', InputArgument::REQUIRED, 'Flag name to test')
            ->addOption('user-id', 'u', InputOption::VALUE_REQUIRED, 'User ID for context')
            ->addOption('session-id', 's', InputOption::VALUE_REQUIRED, 'Session ID for context')
            ->addOption('company-id', 'c', InputOption::VALUE_REQUIRED, 'Company ID for context')
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Email for context')
            ->addOption('country', null, InputOption::VALUE_REQUIRED, 'Country code for context')
            ->addOption('region', null, InputOption::VALUE_REQUIRED, 'Region for context')
            ->addOption('city', null, InputOption::VALUE_REQUIRED, 'City for context')
            ->addOption('ip-address', 'i', InputOption::VALUE_REQUIRED, 'IP address for context')
            ->addOption('context', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Additional context as key=value pairs')
            ->setHelp(<<<'HELP'
The <info>%command.name%</info> command tests feature flag evaluation with specific context.

<info>Test with user ID:</info>
  <info>php %command.full_name% experiments.new_feature --user-id=123</info>

<info>Test with multiple context values:</info>
  <info>php %command.full_name% geo.eu_feature --country=DE --email=user@example.com</info>

<info>Test with custom attributes:</info>
  <info>php %command.full_name% premium.feature --context=subscription_tier=premium --context=account_age_days=45</info>

<info>Test progressive rollout:</info>
  <info>php %command.full_name% rollout.gradual --user-id=123</info>

This command shows:
- Final evaluation result (enabled/disabled)
- Flag configuration
- Applied context
- Strategy used
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $flagName = $input->getArgument('flag');

        // Check if flag exists
        if (!$this->flagService->exists($flagName)) {
            $io->error(sprintf('Flag "%s" not found', $flagName));
            return Command::FAILURE;
        }

        // Build context
        $context = $this->buildContext($input);

        // Get configuration
        $config = $this->flagService->getConfig($flagName);

        // Display flag info
        $io->title('Testing Flag: ' . $flagName);

        $io->section('Flag Configuration');
        $this->displayConfig($config, $io);

        $io->section('Context');
        if (empty($context)) {
            $io->text('<comment>No context provided</comment>');
        } else {
            $this->displayContext($context, $io);
        }

        // Evaluate
        $io->section('Evaluation');
        $result = $this->flagService->isEnabled($flagName, $context);

        if ($result) {
            $io->success('Flag is ENABLED for this context');
        } else {
            $io->error('Flag is DISABLED for this context');
        }

        return Command::SUCCESS;
    }

    /**
     * Build context from input options.
     *
     * @return array<string, mixed>
     */
    private function buildContext(InputInterface $input): array
    {
        $context = [];

        // Add standard context fields
        if ($userId = $input->getOption('user-id')) {
            $context['user_id'] = $userId;
        }

        if ($sessionId = $input->getOption('session-id')) {
            $context['session_id'] = $sessionId;
        }

        if ($companyId = $input->getOption('company-id')) {
            $context['company_id'] = $companyId;
        }

        if ($email = $input->getOption('email')) {
            $context['email'] = $email;
        }

        if ($country = $input->getOption('country')) {
            $context['country'] = $country;
        }

        if ($region = $input->getOption('region')) {
            $context['region'] = $region;
        }

        if ($city = $input->getOption('city')) {
            $context['city'] = $city;
        }

        if ($ipAddress = $input->getOption('ip-address')) {
            $context['ip_address'] = $ipAddress;
        }

        // Add custom context fields
        $customContext = $input->getOption('context');
        if ($customContext) {
            foreach ($customContext as $pair) {
                if (str_contains($pair, '=')) {
                    [$key, $value] = explode('=', $pair, 2);
                    // Try to parse as number
                    if (is_numeric($value)) {
                        $value = str_contains($value, '.') ? (float) $value : (int) $value;
                    }
                    $context[$key] = $value;
                }
            }
        }

        return $context;
    }

    /**
     * Display flag configuration.
     *
     * @param array<string, mixed>|null $config
     */
    private function displayConfig(?array $config, SymfonyStyle $io): void
    {
        if (!$config) {
            $io->text('<error>Failed to load configuration</error>');
            return;
        }

        $rows = [];
        foreach ($config as $key => $value) {
            $rows[] = [$key, $this->formatValue($value)];
        }

        $io->table(['Key', 'Value'], $rows);
    }

    /**
     * Display context.
     *
     * @param array<string, mixed> $context
     */
    private function displayContext(array $context, SymfonyStyle $io): void
    {
        $rows = [];
        foreach ($context as $key => $value) {
            $rows[] = [$key, $this->formatValue($value), gettype($value)];
        }

        $io->table(['Key', 'Value', 'Type'], $rows);
    }

    /**
     * Format value for display.
     */
    private function formatValue(mixed $value): string
    {
        if (is_array($value)) {
            return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        return (string) $value;
    }
}
