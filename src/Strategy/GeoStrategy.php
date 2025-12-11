<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Strategy;

use Pulse\Flags\Core\Enum\FlagStrategy;
use Psr\Log\LoggerInterface;

/**
 * Geographic location-based activation strategy for feature flags.
 *
 * Enables features based on user's geographic location (country, region, city).
 * Useful for regional rollouts, compliance requirements (GDPR, data sovereignty),
 * and market-specific features.
 *
 * Benefits:
 * - Regional feature rollouts
 * - Compliance with geographic regulations (GDPR, CCPA)
 * - Market-specific functionality
 * - A/B testing by region
 *
 * Example configuration (country-based):
 * ```php
 * [
 *     'enabled' => true,
 *     'strategy' => 'geo',
 *     'countries' => ['US', 'CA', 'GB', 'AU'],  // ISO 3166-1 alpha-2 codes
 * ]
 * ```
 *
 * Example configuration (EU countries for GDPR):
 * ```php
 * [
 *     'enabled' => true,
 *     'strategy' => 'geo',
 *     'countries' => [
 *         'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
 *         'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
 *         'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE',
 *     ],
 * ]
 * ```
 *
 * Example configuration (region/city-based):
 * ```php
 * [
 *     'enabled' => true,
 *     'strategy' => 'geo',
 *     'regions' => ['CA', 'NY', 'TX'],  // US states
 *     // OR
 *     'cities' => ['New York', 'Los Angeles', 'Chicago'],
 * ]
 * ```
 *
 * Context requirements:
 * - 'country' (string): ISO 3166-1 alpha-2 country code (e.g., 'US', 'GB')
 * - 'region' (string, optional): Region/state code or name
 * - 'city' (string, optional): City name
 *
 * Note: Geographic data must be provided in context. Common approaches:
 * - CDN edge functions (Cloudflare Workers, AWS Lambda@Edge)
 * - Application middleware using GeoIP2/MaxMind databases
 * - Reverse proxy headers (X-Country-Code)
 */
class GeoStrategy implements StrategyInterface
{
    private ?LoggerInterface $logger;

    /**
     * @param LoggerInterface|null $logger Optional logger for debugging
     */
    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * Determines if the feature should be enabled based on geographic location.
     *
     * Uses OR logic within each category (countries OR regions OR cities).
     * Uses AND logic between categories if multiple are specified.
     *
     * @param array<string, mixed> $config Configuration with 'countries', 'regions', and/or 'cities'
     * @param array<string, mixed> $context Runtime context with geographic data
     * @return bool True if location matches configured rules
     */
    public function isEnabled(array $config, array $context = []): bool
    {
        $countries = $config['countries'] ?? [];
        $regions = $config['regions'] ?? [];
        $cities = $config['cities'] ?? [];

        if (empty($countries) && empty($regions) && empty($cities)) {
            $this->logger?->warning('Geo strategy has no countries, regions, or cities configured');
            return false;
        }

        $matchesCountry = $this->matchesCountry($countries, $context);
        $matchesRegion = $this->matchesRegion($regions, $context);
        $matchesCity = $this->matchesCity($cities, $context);

        // If a category is configured, it must match
        $result = true;

        if (!empty($countries)) {
            $result = $result && $matchesCountry;
        }

        if (!empty($regions)) {
            $result = $result && $matchesRegion;
        }

        if (!empty($cities)) {
            $result = $result && $matchesCity;
        }

        return $result;
    }

    /**
     * Check if user's country matches configured countries.
     *
     * @param array<int, string> $countries Configured countries
     * @param array<string, mixed> $context Runtime context
     * @return bool
     */
    private function matchesCountry(array $countries, array $context): bool
    {
        if (empty($countries)) {
            return true;
        }

        $userCountry = $context['country'] ?? null;

        if ($userCountry === null) {
            $this->logger?->warning('Geo strategy requires country in context');
            return false;
        }

        // Normalize to uppercase for comparison
        $userCountry = strtoupper((string) $userCountry);
        $countries = array_map('strtoupper', $countries);

        $matched = in_array($userCountry, $countries, true);

        if ($matched) {
            $this->logger?->debug('Country matched', [
                'country' => $userCountry,
            ]);
        }

        return $matched;
    }

    /**
     * Check if user's region matches configured regions.
     *
     * @param array<int, string> $regions Configured regions
     * @param array<string, mixed> $context Runtime context
     * @return bool
     */
    private function matchesRegion(array $regions, array $context): bool
    {
        if (empty($regions)) {
            return true;
        }

        $userRegion = $context['region'] ?? null;

        if ($userRegion === null) {
            $this->logger?->warning('Geo strategy requires region in context when regions configured');
            return false;
        }

        // Normalize to uppercase for comparison
        $userRegion = strtoupper((string) $userRegion);
        $regions = array_map('strtoupper', $regions);

        $matched = in_array($userRegion, $regions, true);

        if ($matched) {
            $this->logger?->debug('Region matched', [
                'region' => $userRegion,
            ]);
        }

        return $matched;
    }

    /**
     * Check if user's city matches configured cities.
     *
     * @param array<int, string> $cities Configured cities
     * @param array<string, mixed> $context Runtime context
     * @return bool
     */
    private function matchesCity(array $cities, array $context): bool
    {
        if (empty($cities)) {
            return true;
        }

        $userCity = $context['city'] ?? null;

        if ($userCity === null) {
            $this->logger?->warning('Geo strategy requires city in context when cities configured');
            return false;
        }

        // Case-insensitive comparison for city names
        $userCity = strtolower((string) $userCity);
        $cities = array_map('strtolower', $cities);

        $matched = in_array($userCity, $cities, true);

        if ($matched) {
            $this->logger?->debug('City matched', [
                'city' => $userCity,
            ]);
        }

        return $matched;
    }

    /**
     * Returns the unique identifier for this strategy.
     *
     * @return string The strategy name 'geo'
     */
    public function getName(): string
    {
        return FlagStrategy::GEO->value;
    }
}
