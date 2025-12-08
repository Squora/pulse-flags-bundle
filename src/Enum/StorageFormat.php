<?php

declare(strict_types=1);

namespace Pulse\FlagsBundle\Enum;

/**
 * Storage format types for feature flags configuration.
 *
 * Defines the supported file formats for storing permanent (read-only)
 * feature flags configuration.
 */
enum StorageFormat: string
{
    case YAML = 'yaml';
    case PHP = 'php';
}
