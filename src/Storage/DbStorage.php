<?php

declare(strict_types=1);

namespace Pulse\FlagsBundle\Storage;

use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;

class DbStorage implements StorageInterface
{
    private ?PDO $pdo = null;
    private string $dsn;
    private ?string $user;
    private ?string $password;
    private string $table;
    private string $driver;

    public function __construct(string $dsn, ?string $user = null, ?string $password = null, string $table = 'pulse_feature_flags')
    {
        $parsed = $this->parseDoctrineDsn($dsn, $user, $password);

        $this->dsn = $parsed['dsn'];
        $this->user = $parsed['user'];
        $this->password = $parsed['password'];
        $this->table = $table;
        $this->driver = $this->extractDriver($this->dsn);
    }

    private function extractDriver(string $dsn): string
    {
        $parts = explode(':', $dsn, 2);

        return strtolower($parts[0] ?? 'mysql');
    }

    private function getDefaultPort(string $driver): int
    {
        return match($driver) {
            'mysql', 'mariadb' => 3306,
            'pgsql', 'postgresql' => 5432,
            'sqlsrv' => 1433,
            default => 3306
        };
    }

    /**
     * Convert Doctrine URL format to PDO DSN format
     * Handles: mysql://user:pass@host:port/dbname?params
     * Returns: ['dsn' => 'mysql:host=...', 'user' => '...', 'password' => '...']
     */
    private function parseDoctrineDsn(string $dsn, ?string $user, ?string $password): array
    {
        if (!str_contains($dsn, '://')) {
            return ['dsn' => $dsn, 'user' => $user, 'password' => $password];
        }

        $parsed = parse_url($dsn);

        if ($parsed === false || !isset($parsed['scheme'])) {
            throw new InvalidArgumentException("Invalid DSN format: {$dsn}");
        }

        $driver = $parsed['scheme'];
        $host = $parsed['host'] ?? 'localhost';
        $port = $parsed['port'] ?? $this->getDefaultPort($driver);
        $dbname = isset($parsed['path']) ? ltrim($parsed['path'], '/') : '';

        $extractedUser = $user ?? ($parsed['user'] ?? null);
        $extractedPassword = $password ?? ($parsed['pass'] ?? null);

        $pdoDsn = sprintf('%s:host=%s;port=%d', $driver, $host, $port);

        if ($dbname) {
            $pdoDsn .= ';dbname=' . $dbname;
        }

        if (in_array($driver, ['mysql', 'mariadb'])) {
            $pdoDsn .= ';charset=utf8mb4';
        }

        return [
            'dsn' => $pdoDsn,
            'user' => $extractedUser,
            'password' => $extractedPassword,
        ];
    }

    private function getConnection(): PDO
    {
        if (null === $this->pdo) {
            try {
                $this->pdo = new PDO($this->dsn, $this->user, $this->password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
            } catch (PDOException $e) {
                throw new RuntimeException(sprintf('Failed to connect to database: %s', $e->getMessage()));
            }
        }

        return $this->pdo;
    }

    public function initializeTable(): void
    {
        if ($this->driver === 'pgsql') {
            $sql = sprintf("
                CREATE TABLE IF NOT EXISTS %s (
                    name VARCHAR(255) PRIMARY KEY,
                    config JSONB NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ", $this->table);
        } elseif ($this->driver === 'sqlite') {
            $sql = sprintf("
                CREATE TABLE IF NOT EXISTS %s (
                    name VARCHAR(255) PRIMARY KEY,
                    config TEXT NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ", $this->table);
        } else { // MySQL
            $sql = sprintf("
                CREATE TABLE IF NOT EXISTS `%s` (
                    `name` VARCHAR(255) PRIMARY KEY,
                    `config` JSON NOT NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_updated_at (`updated_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ", $this->table);
        }

        $this->getConnection()->exec($sql);
    }

    public function get(string $name): ?array
    {
        $stmt = $this->getConnection()->prepare(sprintf("SELECT config FROM %s WHERE name = :name LIMIT 1", $this->table));
        $stmt->execute(['name' => $name]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        $decoded = json_decode($row['config'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException(sprintf('Failed to decode JSON for flag "%s": %s', $name, json_last_error_msg()));
        }

        return $decoded;
    }

    public function set(string $name, array $config): void
    {
        $configJson = json_encode($config, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        if ($this->driver === 'pgsql') {
            $sql = sprintf("
                INSERT INTO %s (name, config, created_at, updated_at)
                VALUES (:name, :config, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                ON CONFLICT (name) DO UPDATE SET
                    config = EXCLUDED.config,
                    updated_at = CURRENT_TIMESTAMP
            ", $this->table);
        } elseif ($this->driver === 'sqlite') {
            $sql = sprintf("
                INSERT OR REPLACE INTO %s (name, config, created_at, updated_at)
                VALUES (:name, :config, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ", $this->table);
        } else { // MySQL
            $sql = sprintf("
                INSERT INTO %s (name, config)
                VALUES (:name, :config)
                ON DUPLICATE KEY UPDATE
                    config = VALUES(config),
                    updated_at = CURRENT_TIMESTAMP
            ", $this->table);
        }

        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([
            'name' => $name,
            'config' => $configJson,
        ]);
    }

    public function remove(string $name): void
    {
        $stmt = $this->getConnection()->prepare(sprintf("DELETE FROM %s WHERE name = :name", $this->table));
        $stmt->execute(['name' => $name]);
    }

    public function has(string $name): bool
    {
        $stmt = $this->getConnection()->prepare(sprintf("SELECT 1 FROM %s WHERE name = :name LIMIT 1", $this->table));
        $stmt->execute(['name' => $name]);

        return (bool) $stmt->fetchColumn();
    }

    public function all(): array
    {
        $stmt = $this->getConnection()->query(sprintf("SELECT name, config FROM %s ORDER BY name", $this->table));
        if ($stmt === false) {
            return [];
        }
        $rows = $stmt->fetchAll();
        $result = [];

        foreach ($rows as $row) {
            $decoded = json_decode($row['config'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $result[$row['name']] = $decoded;
            }
        }

        return $result;
    }

    public function paginate(int $page = 1, int $limit = 50): array
    {
        $limit = min($limit, 100);
        $page = max($page, 1);
        $offset = ($page - 1) * $limit;

        $countStmt = $this->getConnection()->query(sprintf("SELECT COUNT(*) FROM %s", $this->table));
        if ($countStmt === false) {
            return ['flags' => [], 'pagination' => ['page' => $page, 'limit' => $limit, 'total' => 0, 'pages' => 0]];
        }
        $total = (int) $countStmt->fetchColumn();

        $sql = sprintf("SELECT name, config FROM %s ORDER BY name LIMIT :limit OFFSET :offset", $this->table);
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $flags = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $decoded = json_decode($row['config'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $flags[$row['name']] = $decoded;
            }
        }

        return [
            'flags' => $flags,
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
        if ($this->driver === 'sqlite') {
            $this->getConnection()->exec(sprintf("DELETE FROM %s", $this->table));
        } else {
            $this->getConnection()->exec(sprintf("TRUNCATE TABLE %s", $this->table));
        }
    }

    /**
     * Get the underlying PDO connection (for advanced usage)
     */
    public function getPdo(): PDO
    {
        return $this->getConnection();
    }

    /**
     * Get database driver name
     */
    public function getDriver(): string
    {
        return $this->driver;
    }
}
