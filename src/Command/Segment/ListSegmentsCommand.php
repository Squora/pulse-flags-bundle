<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Command\Segment;

use Pulse\Flags\Core\Segment\SegmentRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command to list all available segments.
 */
#[AsCommand(
    name: 'pulse:flags:segments:list',
    description: 'List all available feature flag segments'
)]
class ListSegmentsCommand extends Command
{
    public function __construct(
        private readonly SegmentRepository $segmentRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('type', 't', InputOption::VALUE_REQUIRED, 'Filter by type (static, dynamic)')
            ->addOption('detailed', 'd', InputOption::VALUE_NONE, 'Show detailed information')
            ->setHelp(<<<'HELP'
The <info>%command.name%</info> command lists all available segments.

<info>List all segments:</info>
  <info>php %command.full_name%</info>

<info>Filter by type:</info>
  <info>php %command.full_name% --type=static</info>

<info>Show detailed information:</info>
  <info>php %command.full_name% --detailed</info>

Segments are reusable groups of users that can be targeted by feature flags.
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $typeFilter = $input->getOption('type');
        $detailed = $input->getOption('detailed');

        $io->title('Feature Flag Segments');

        // Get all segments
        $segments = $this->segmentRepository->all();

        if (empty($segments)) {
            $io->warning('No segments found');
            return Command::SUCCESS;
        }

        // Filter by type if specified
        if ($typeFilter) {
            $segments = array_filter($segments, function ($segment) use ($typeFilter) {
                return ($segment['type'] ?? 'static') === $typeFilter;
            });

            if (empty($segments)) {
                $io->warning(sprintf('No segments found with type: %s', $typeFilter));
                return Command::SUCCESS;
            }
        }

        // Display segments
        if ($detailed) {
            $this->displayDetailedSegments($segments, $io);
        } else {
            $this->displaySimpleSegments($segments, $io);
        }

        $io->success(sprintf('Found %d segment(s)', count($segments)));

        return Command::SUCCESS;
    }

    /**
     * Display segments in simple table format.
     *
     * @param array<string, array<string, mixed>> $segments
     */
    private function displaySimpleSegments(array $segments, SymfonyStyle $io): void
    {
        $rows = [];
        foreach ($segments as $name => $config) {
            $type = $config['type'] ?? 'static';
            $size = $this->getSegmentSize($config);

            $rows[] = [$name, $type, $size];
        }

        $io->table(['Segment Name', 'Type', 'Size'], $rows);
    }

    /**
     * Display segments with detailed information.
     *
     * @param array<string, array<string, mixed>> $segments
     */
    private function displayDetailedSegments(array $segments, SymfonyStyle $io): void
    {
        foreach ($segments as $name => $config) {
            $io->section($name);

            $type = $config['type'] ?? 'static';
            $io->writeln(sprintf('<info>Type:</info> %s', $type));

            if ($type === 'static') {
                $userIds = $config['user_ids'] ?? [];
                $io->writeln(sprintf('<info>User IDs:</info> %d users', count($userIds)));

                if (count($userIds) <= 10) {
                    $io->listing($userIds);
                } else {
                    $io->listing(array_slice($userIds, 0, 10));
                    $io->writeln(sprintf('<comment>... and %d more</comment>', count($userIds) - 10));
                }
            } elseif ($type === 'dynamic') {
                $condition = $config['condition'] ?? 'unknown';
                $value = $config['value'] ?? '';
                $io->writeln(sprintf('<info>Condition:</info> %s', $condition));
                $io->writeln(sprintf('<info>Value:</info> %s', $value));

                if (isset($config['operator'])) {
                    $io->writeln(sprintf('<info>Operator:</info> %s', $config['operator']));
                }
            }

            $io->newLine();
        }
    }

    /**
     * Get segment size description.
     *
     * @param array<string, mixed> $config
     */
    private function getSegmentSize(array $config): string
    {
        $type = $config['type'] ?? 'static';

        if ($type === 'static') {
            $count = count($config['user_ids'] ?? []);
            return sprintf('%d user%s', $count, $count !== 1 ? 's' : '');
        }

        return 'Dynamic';
    }
}
