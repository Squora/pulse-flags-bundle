<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Command\Query;

use Pulse\Flags\Core\Service\FeatureFlagServiceInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command to export all feature flags.
 *
 * Supports JSON and YAML formats.
 */
#[AsCommand(
    name: 'pulse:flags:export',
    description: 'Export all feature flags to file or stdout'
)]
class ExportFlagsCommand extends Command
{
    public function __construct(
        private readonly FeatureFlagServiceInterface $flagService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Export format (json, yaml)', 'json')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output file path (if omitted, prints to stdout)')
            ->addOption('pretty', 'p', InputOption::VALUE_NONE, 'Pretty print output (JSON only)')
            ->setHelp(<<<'HELP'
The <info>%command.name%</info> command exports all feature flags.

<info>Export to stdout (JSON):</info>
  <info>php %command.full_name%</info>

<info>Export to file (pretty JSON):</info>
  <info>php %command.full_name% --output=flags.json --pretty</info>

<info>Export to YAML:</info>
  <info>php %command.full_name% --format=yaml --output=flags.yaml</info>

The export includes all flag configurations from all sources.
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $format = $input->getOption('format');
        $outputFile = $input->getOption('output');
        $pretty = $input->getOption('pretty');

        // Get all flags
        $flags = $this->flagService->all();

        if (empty($flags)) {
            $io->warning('No flags found to export');
            return Command::SUCCESS;
        }

        // Format output
        $exportedContent = match ($format) {
            'yaml' => $this->exportToYaml($flags),
            'json' => $this->exportToJson($flags, $pretty),
            default => throw new \InvalidArgumentException(sprintf('Unsupported format: %s', $format)),
        };

        // Write to file or stdout
        if ($outputFile) {
            file_put_contents($outputFile, $exportedContent);
            $io->success(sprintf('Exported %d flag(s) to %s', count($flags), $outputFile));
        } else {
            $output->writeln($exportedContent);
        }

        return Command::SUCCESS;
    }

    /**
     * Export flags to JSON format.
     *
     * @param array<string, array<string, mixed>> $flags
     */
    private function exportToJson(array $flags, bool $pretty): string
    {
        $options = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        if ($pretty) {
            $options |= JSON_PRETTY_PRINT;
        }

        return json_encode($flags, $options);
    }

    /**
     * Export flags to YAML format.
     *
     * @param array<string, array<string, mixed>> $flags
     */
    private function exportToYaml(array $flags): string
    {
        $yaml = '';

        foreach ($flags as $name => $config) {
            $yaml .= $name . ":\n";
            $yaml .= $this->arrayToYaml($config, 1);
            $yaml .= "\n";
        }

        return $yaml;
    }

    /**
     * Convert array to YAML format (simple implementation).
     *
     * @param array<string, mixed> $data
     */
    private function arrayToYaml(array $data, int $indent = 0): string
    {
        $yaml = '';
        $indentStr = str_repeat('    ', $indent);

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if (empty($value)) {
                    $yaml .= $indentStr . $key . ": []\n";
                } elseif (array_is_list($value)) {
                    $yaml .= $indentStr . $key . ":\n";
                    foreach ($value as $item) {
                        if (is_array($item)) {
                            $yaml .= $indentStr . "    -\n";
                            $yaml .= $this->arrayToYaml($item, $indent + 2);
                        } else {
                            $yaml .= $indentStr . "    - " . $this->formatYamlValue($item) . "\n";
                        }
                    }
                } else {
                    $yaml .= $indentStr . $key . ":\n";
                    $yaml .= $this->arrayToYaml($value, $indent + 1);
                }
            } else {
                $yaml .= $indentStr . $key . ': ' . $this->formatYamlValue($value) . "\n";
            }
        }

        return $yaml;
    }

    /**
     * Format value for YAML.
     */
    private function formatYamlValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        if (is_string($value) && (str_contains($value, ':') || str_contains($value, '#'))) {
            return "'" . str_replace("'", "''", $value) . "'";
        }

        return (string) $value;
    }
}
