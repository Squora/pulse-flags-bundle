<?php

declare(strict_types=1);

namespace Pulse\FlagsBundle\Constants;

/**
 * Pagination configuration constants.
 *
 * Centralizes pagination limits to ensure consistent behavior
 * across the application.
 */
final class Pagination
{
    /**
     * Default number of items per page.
     */
    public const DEFAULT_LIMIT = 50;

    /**
     * Maximum number of items per page.
     * Used to prevent excessive memory usage and improve performance.
     */
    public const MAX_LIMIT = 100;

    /**
     * Default page number (first page).
     */
    public const DEFAULT_PAGE = 1;

    /**
     * Minimum page number.
     */
    public const MIN_PAGE = 1;

    /**
     * Minimum items per page.
     */
    public const MIN_LIMIT = 1;

    private function __construct()
    {
        // Prevent instantiation
    }
}
