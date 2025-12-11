<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Command\Segment;

use Pulse\Flags\Core\Segment\SegmentRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command to create a new segment.
 */
#[AsCommand(
    name: 'pulse:flags:segments:create',
    description: 'Create a new feature flag segment'
)]
class CreateSegmentCommand extends Command
{
    public function __construct(
        private readonly SegmentRepository $segmentRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'Segment name')
            ->addOption('type', 't', InputOption::VALUE_REQUIRED, 'Segment type (static, dynamic)', 'static')
            ->addOption('user-ids', 'u', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'User IDs for static segment')
            ->addOption('condition', 'c', InputOption::VALUE_REQUIRED, 'Condition for dynamic segment')
            ->addOption('value', null, InputOption::VALUE_REQUIRED, 'Value for dynamic segment condition')
            ->addOption('operator', 'o', InputOption::VALUE_REQUIRED, 'Operator for dynamic segment (equals, in, contains, etc.)')
            ->setHelp(<<<'HELP'
The <info>%command.name%</info> command creates a new segment.

<info>Create static segment with user IDs:</info>
  <info>php %command.full_name% premium_users --user-ids=1 --user-ids=2 --user-ids=3</info>

<info>Create dynamic segment (email domain):</info>
  <info>php %command.full_name% internal_team --type=dynamic --condition=email_domain --value=company.com</info>

<info>Create dynamic segment (country):</info>
  <info>php %command.full_name% eu_users --type=dynamic --condition=country --operator=in --value=DE,FR,GB</info>

Segments are reusable groups that can be referenced by multiple feature flags.
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = $input->getArgument('name');
        $type = $input->getOption('type');

        // Validate segment name
        if (!preg_match('/^[a-z0-9_]+$/', $name)) {
            $io->error('Segment name must contain only lowercase letters, numbers, and underscores');
            return Command::FAILURE;
        }

        // Check if segment already exists
        if ($this->segmentRepository->exists($name)) {
            $io->error(sprintf('Segment "%s" already exists', $name));
            return Command::FAILURE;
        }

        // Build segment configuration based on type
        try {
            $config = match ($type) {
                'static' => $this->buildStaticSegment($input, $io),
                'dynamic' => $this->buildDynamicSegment($input, $io),
                default => throw new \InvalidArgumentException(sprintf('Invalid segment type: %s', $type)),
            };
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        // Display segment preview
        $io->title(sprintf('Creating Segment: %s', $name));
        $io->section('Configuration');

        $rows = [];
        foreach ($config as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value);
            }
            $rows[] = [$key, (string) $value];
        }

        $io->table(['Key', 'Value'], $rows);

        // Confirm creation
        if (!$io->confirm('Create this segment?', true)) {
            $io->warning('Segment creation cancelled');
            return Command::SUCCESS;
        }

        // Create segment
        try {
            $this->segmentRepository->create($name, $config);
            $io->success(sprintf('Segment "%s" created successfully', $name));
        } catch (\Exception $e) {
            $io->error(sprintf('Failed to create segment: %s', $e->getMessage()));
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Build static segment configuration.
     *
     * @return array<string, mixed>
     */
    private function buildStaticSegment(InputInterface $input, SymfonyStyle $io): array
    {
        $userIds = $input->getOption('user-ids');

        if (empty($userIds)) {
            throw new \InvalidArgumentException('Static segment requires at least one user ID (use --user-ids)');
        }

        return [
            'type' => 'static',
            'user_ids' => $userIds,
        ];
    }

    /**
     * Build dynamic segment configuration.
     *
     * @return array<string, mixed>
     */
    private function buildDynamicSegment(InputInterface $input, SymfonyStyle $io): array
    {
        $condition = $input->getOption('condition');
        $value = $input->getOption('value');
        $operator = $input->getOption('operator');

        if (!$condition) {
            throw new \InvalidArgumentException('Dynamic segment requires a condition (use --condition)');
        }

        if (!$value) {
            throw new \InvalidArgumentException('Dynamic segment requires a value (use --value)');
        }

        $config = [
            'type' => 'dynamic',
            'condition' => $condition,
            'value' => $value,
        ];

        if ($operator) {
            $config['operator'] = $operator;
        }

        return $config;
    }
}
