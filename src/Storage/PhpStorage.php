<?php

declare(strict_types=1);

namespace Pulse\FlagsBundle\Storage;

use Pulse\FlagsBundle\Constants\Pagination;

/**
 * PHP file-based storage for feature flags
 *
 * Stores flags in PHP files that return arrays.
 * This is useful for permanent flags that need version control.
 */
class PhpStorage implements StorageInterface
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $flags;

    /**
     * @param string|null $filePath Path to PHP file (if null, uses in-memory storage for testing)
     */
    public function __construct(
        private readonly ?string $filePath = null
    ) {
        if ($filePath !== null && file_exists($filePath)) {
            $this->flags = include $filePath;
            if (!is_array($this->flags)) {
                $this->flags = [];
            }
        } else {
            $this->flags = [];
        }
    }

    public function get(string $name): ?array
    {
        return $this->flags[$name] ?? null;
    }

    public function set(string $name, array $config): void
    {
        $this->flags[$name] = $config;
        $this->persist();
    }

    public function remove(string $name): void
    {
        unset($this->flags[$name]);
        $this->persist();
    }

    public function has(string $name): bool
    {
        return isset($this->flags[$name]);
    }

    public function all(): array
    {
        return $this->flags;
    }

    public function paginate(int $page = Pagination::DEFAULT_PAGE, int $limit = Pagination::DEFAULT_LIMIT): array
    {
        $limit = min($limit, Pagination::MAX_LIMIT);
        $page = max($page, Pagination::MIN_PAGE);
        $offset = ($page - Pagination::MIN_PAGE) * $limit;

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

    public function clear(): void
    {
        $this->flags = [];
        $this->persist();
    }

    /**
     * Persist flags to PHP file
     */
    private function persist(): void
    {
        if ($this->filePath === null) {
            return; // In-memory mode for testing
        }

        $dir = dirname($this->filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $export = var_export($this->flags, true);
        $content = <<<PHP
<?php

declare(strict_types=1);

return {$export};

PHP;

        file_put_contents($this->filePath, $content, LOCK_EX);
    }
}
