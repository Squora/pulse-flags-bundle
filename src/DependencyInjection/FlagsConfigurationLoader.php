<?php

declare(strict_types=1);

namespace Pulse\FlagsBundle\DependencyInjection;

use DateTime;
use Exception;
use InvalidArgumentException;
use Symfony\Component\Yaml\Yaml;

class FlagsConfigurationLoader
{
    /**
     * Load all feature flag configurations from config/pulse_flags directory
     *
     * All flags loaded from config files are considered permanent (read-only).
     * Files are namespaced by filename (without extension)
     * Example: config/pulse_flags/core.yaml -> flags prefixed with "core."
     *
     * @param string $configDir Config directory path
     * @param string $format Storage format (yaml, php)
     * @return array<string, array<string, mixed>> Permanent flags configuration
     */
    public static function loadFlagsFromDirectory(string $configDir, string $format = 'yaml'): array
    {
        $flagsDir = $configDir . '/packages/pulse_flags';

        if (!is_dir($flagsDir)) {
            return [];
        }

        return match ($format) {
            'yaml' => self::loadYamlFlags($flagsDir),
            'php' => self::loadPhpFlags($flagsDir),
            default => [],
        };
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function loadYamlFlags(string $flagsDir): array
    {
        $permanentFlags = [];
        $files = glob($flagsDir . '/*.yaml');

        if ($files === false) {
            return [];
        }

        foreach ($files as $file) {
            $namespace = basename($file, '.yaml');

            if (str_ends_with($namespace, '.local')) {
                continue;
            }

            $flags = Yaml::parseFile($file);

            if (!is_array($flags)) {
                continue;
            }

            foreach ($flags as $flagName => $config) {
                $fullFlagName = $namespace . '.' . $flagName;
                self::validateFlagHasEnabled($fullFlagName, $config);
                self::removeDeprecatedStorageField($fullFlagName, $config, $file);
                $permanentFlags[$fullFlagName] = $config;
            }

            $localFile = str_replace('.yaml', '.local.yaml', $file);
            if (file_exists($localFile)) {
                $localFlags = Yaml::parseFile($localFile);

                if (is_array($localFlags)) {
                    foreach ($localFlags as $flagName => $config) {
                        $fullFlagName = $namespace . '.' . $flagName;
                        self::removeDeprecatedStorageField($fullFlagName, $config, $localFile);
                        $permanentFlags[$fullFlagName] = array_merge(
                            $permanentFlags[$fullFlagName] ?? [],
                            $config
                        );
                    }
                }
            }
        }

        return $permanentFlags;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function loadPhpFlags(string $flagsDir): array
    {
        $permanentFlags = [];
        $files = glob($flagsDir . '/*.php');

        if ($files === false) {
            return [];
        }

        foreach ($files as $file) {
            $namespace = basename($file, '.php');

            if (str_ends_with($namespace, '.local')) {
                continue;
            }

            $flags = include $file;

            if (!is_array($flags)) {
                continue;
            }

            foreach ($flags as $flagName => $config) {
                $fullFlagName = $namespace . '.' . $flagName;
                self::validateFlagHasEnabled($fullFlagName, $config);
                self::removeDeprecatedStorageField($fullFlagName, $config, $file);
                $permanentFlags[$fullFlagName] = $config;
            }

            $localFile = str_replace('.php', '.local.php', $file);
            if (file_exists($localFile)) {
                $localFlags = include $localFile;

                if (is_array($localFlags)) {
                    foreach ($localFlags as $flagName => $config) {
                        $fullFlagName = $namespace . '.' . $flagName;
                        self::removeDeprecatedStorageField($fullFlagName, $config, $localFile);
                        $permanentFlags[$fullFlagName] = array_merge(
                            $permanentFlags[$fullFlagName] ?? [],
                            $config
                        );
                    }
                }
            }
        }

        return $permanentFlags;
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function validateFlagHasEnabled(string $flagName, array $config): void
    {
        if (!isset($config['enabled'])) {
            throw new InvalidArgumentException(
                sprintf(
                    'Flag "%s" must have an "enabled" field in its configuration.',
                    $flagName,
                )
            );
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function removeDeprecatedStorageField(string $flagName, array &$config, string $file): void
    {
        if (isset($config['storage'])) {
            trigger_error(
                sprintf(
                    'The "storage" field in flag "%s" (%s) is deprecated and will be ignored. ' .
                    'All flags defined in config files are now automatically permanent (read-only).',
                    $flagName,
                    $file
                ),
                E_USER_DEPRECATED
            );
            unset($config['storage']);
        }
    }

    /**
     * @param array<string, mixed> $config
     * @return array<int, string>
     */
    public static function validateFlagConfig(string $name, array $config): array
    {
        $errors = [];

        $validStrategies = ['simple', 'percentage', 'user_id', 'date_range', 'composite'];
        $strategy = $config['strategy'] ?? 'simple';

        if (!in_array($strategy, $validStrategies, true)) {
            $errors[] = sprintf(
                'Invalid strategy "%s" for flag "%s". Valid strategies: %s',
                $strategy,
                $name,
                implode(', ', $validStrategies)
            );
        }

        if (isset($config['percentage'])) {
            $percentage = $config['percentage'];
            if (!is_int($percentage) || $percentage < 0 || $percentage > 100) {
                $errors[] = sprintf(
                    'Percentage for flag "%s" must be an integer between 0 and 100',
                    $name
                );
            }
        }

        if (isset($config['start_date'])) {
            try {
                new DateTime($config['start_date']);
            } catch (Exception $e) {
                $errors[] = sprintf(
                    'Invalid start_date for flag "%s": %s',
                    $name,
                    $e->getMessage()
                );
            }
        }

        if (isset($config['end_date'])) {
            try {
                new DateTime($config['end_date']);
            } catch (Exception $e) {
                $errors[] = sprintf(
                    'Invalid end_date for flag "%s": %s',
                    $name,
                    $e->getMessage()
                );
            }
        }

        return $errors;
    }
}
