<?php

declare(strict_types=1);

namespace Pulse\FlagsBundle\Enum;

/**
 * Feature flag status.
 *
 * Represents the enabled/disabled state of a feature flag.
 *
 * @example Create status from boolean
 * $status = FlagStatus::fromBool(true);
 *
 * @example Convert status to boolean
 * $enabled = $status->toBool(); // true
 *
 * @example Get human-readable label
 * $label = $status->label(); // "Enabled"
 *
 * @example Use in configuration
 * $config = ['enabled' => FlagStatus::ENABLED->toBool()];
 */
enum FlagStatus
{
    /**
     * Flag is enabled - feature is active.
     */
    case ENABLED;

    /**
     * Flag is disabled - feature is inactive.
     */
    case DISABLED;

    /**
     * Converts enum value to boolean.
     *
     * @return bool True if enabled, false if disabled
     */
    public function toBool(): bool
    {
        return $this === self::ENABLED;
    }

    /**
     * Creates enum from boolean value.
     *
     * @param bool $enabled True for enabled, false for disabled
     * @return self FlagStatus enum value
     */
    public static function fromBool(bool $enabled): self
    {
        return $enabled ? self::ENABLED : self::DISABLED;
    }

    /**
     * Returns human-readable label for the status.
     *
     * @return string Status label
     */
    public function label(): string
    {
        return match ($this) {
            self::ENABLED => 'Enabled',
            self::DISABLED => 'Disabled',
        };
    }
}
