<?php

declare(strict_types=1);

namespace Pulse\FlagsBundle\Storage;

use LogicException;

/**
 * Read-only YAML file storage for feature flags.
 *
 * Loads feature flags from YAML files in a directory. This storage is read-only
 * and throws LogicException on any write operations. Primarily used for permanent
 * (configuration-based) flags but can also be used for persistent flags if
 * modification at runtime is not needed.
 *
 * File structure expected:
 * ```yaml
 * # flags_dir/core.yaml
 * flag_name:
 *   enabled: true
 *   strategy: percentage
 *   percentage: 50
 * ```
 *
 * Flags are namespaced by filename: core.yaml creates flags like "core.flag_name"
 *
 * Note: Requires YAML PHP extension to be installed.
 */
class YamlStorage implements StorageInterface
{
    /**
     * In-memory cache of loaded flags.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $flags = [];

    /**
     * Loads all YAML files from the specified directory.
     *
     * @param string $flagsDir Directory containing YAML flag definition files
     */
    public function __construct(string $flagsDir)
    {
        $files = glob($flagsDir . '/*.yaml');
        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            $yaml = yaml_parse_file($file);
            $namespace = pathinfo($file, PATHINFO_FILENAME);

            foreach (($yaml['pulse_flags']['flags'] ?? $yaml) as $flagName => $config) {
                $fullName = $namespace . '.' . $flagName;
                $this->flags[$fullName] = $config;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $name): ?array
    {
        return $this->flags[$name] ?? null;
    }

    /**
     * {@inheritdoc}
     *
     * @throws LogicException Always throws as YamlStorage is read-only
     */
    public function set(string $name, array $config): void
    {
        throw new LogicException('YamlStorage is read-only.');
    }

    /**
     * {@inheritdoc}
     *
     * @throws LogicException Always throws as YamlStorage is read-only
     */
    public function remove(string $name): void
    {
        throw new LogicException('YamlStorage is read-only.');
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $name): bool
    {
        return isset($this->flags[$name]);
    }

    /**
     * {@inheritdoc}
     */
    public function all(): array
    {
        return $this->flags;
    }

    /**
     * {@inheritdoc}
     *
     * Returns paginated flags from YAML storage.
     * Note: Pagination is performed in-memory as YAML is read-only.
     */
    public function paginate(int $page = 1, int $limit = 50): array
    {
        $limit = min($limit, 100);
        $page = max($page, 1);
        $offset = ($page - 1) * $limit;

        $total = count($this->flags);
        $flagsSlice = array_slice($this->flags, $offset, $limit, true);

        return [
            'flags' => $flagsSlice,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'pages' => (int) ceil($total / $limit),
                'limit' => $limit,
            ],
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @throws LogicException Always throws as YamlStorage is read-only
     */
    public function clear(): void
    {
        throw new LogicException('YamlStorage is read-only.');
    }
}
