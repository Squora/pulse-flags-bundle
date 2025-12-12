<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Strategy;

use Pulse\Flags\Core\Enum\FlagStrategy;

/**
 * IP address-based activation strategy for feature flags.
 *
 * Enables features based on user's IP address, supporting both
 * individual IP whitelists and CIDR notation for IP ranges.
 *
 * Benefits:
 * - Internal testing without affecting production users
 * - Office/VPN-only features
 * - Regional access control
 * - Security testing and staging access
 *
 * Example configuration (IP whitelist):
 * ```php
 * [
 *     'enabled' => true,
 *     'strategy' => 'ip',
 *     'whitelist_ips' => [
 *         '203.0.113.42',      // Single IP
 *         '198.51.100.10',     // Another IP
 *     ],
 * ]
 * ```
 *
 * Example configuration (IP ranges with CIDR):
 * ```php
 * [
 *     'enabled' => true,
 *     'strategy' => 'ip',
 *     'ip_ranges' => [
 *         '10.0.0.0/8',        // Private network
 *         '192.168.0.0/16',    // Local network
 *         '172.16.0.0/12',     // Private range
 *     ],
 * ]
 * ```
 *
 * Example configuration (mixed):
 * ```php
 * [
 *     'enabled' => true,
 *     'strategy' => 'ip',
 *     'whitelist_ips' => ['203.0.113.42'],
 *     'ip_ranges' => ['10.0.0.0/8', '192.168.0.0/16'],
 * ]
 * ```
 *
 * Context requirements:
 * - 'ip_address' (required): The user's IP address (IPv4 or IPv6)
 *
 * Supported formats:
 * - IPv4: 192.168.1.1
 * - IPv6: 2001:0db8:85a3:0000:0000:8a2e:0370:7334
 * - CIDR: 192.168.0.0/16, 2001:db8::/32
 */
class IpStrategy implements StrategyInterface
{
    /**
     * Determines if the feature should be enabled based on IP address.
     *
     * Uses OR logic: if IP matches any whitelist entry or range, returns true.
     *
     * @param array<string, mixed> $config Configuration with 'whitelist_ips' and/or 'ip_ranges'
     * @param array<string, mixed> $context Runtime context with 'ip_address'
     * @return bool True if IP matches any configured rule
     */
    public function isEnabled(array $config, array $context = []): bool
    {
        $ipAddress = $context['ip_address'] ?? null;

        if ($ipAddress === null || !is_string($ipAddress)) {
            return false;
        }

        $whitelistIps = $config['whitelist_ips'] ?? [];
        $ipRanges = $config['ip_ranges'] ?? [];

        if (empty($whitelistIps) && empty($ipRanges)) {
            return false;
        }

        // Check whitelist IPs (exact match)
        if (!empty($whitelistIps) && in_array($ipAddress, $whitelistIps, true)) {
            return true;
        }

        // Check IP ranges (CIDR notation)
        if (!empty($ipRanges)) {
            foreach ($ipRanges as $range) {
                if ($this->ipInRange($ipAddress, $range)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if an IP address is within a CIDR range.
     *
     * Supports both IPv4 and IPv6.
     *
     * @param string $ip The IP address to check
     * @param string $range The CIDR range (e.g., '192.168.0.0/16')
     * @return bool True if IP is in range
     */
    private function ipInRange(string $ip, string $range): bool
    {
        if (!str_contains($range, '/')) {
            // Not CIDR notation, treat as exact match
            return $ip === $range;
        }

        [$subnet, $bits] = explode('/', $range, 2);
        $bits = (int) $bits;

        // Convert IPs to binary
        $ipBinary = @inet_pton($ip);
        $subnetBinary = @inet_pton($subnet);

        if ($ipBinary === false || $subnetBinary === false) {
            return false;
        }

        // IPs must be same version (IPv4 or IPv6)
        if (strlen($ipBinary) !== strlen($subnetBinary)) {
            return false;
        }

        // Calculate number of full bytes and remaining bits
        $bytesCount = strlen($ipBinary);
        $maxBits = $bytesCount * 8;

        // Validate bits
        if ($bits < 0 || $bits > $maxBits) {
            return false;
        }

        // Compare bit by bit
        $fullBytes = (int) floor($bits / 8);
        $remainingBits = $bits % 8;

        // Compare full bytes
        if ($fullBytes > 0) {
            $ipPrefix = substr($ipBinary, 0, $fullBytes);
            $subnetPrefix = substr($subnetBinary, 0, $fullBytes);

            if ($ipPrefix !== $subnetPrefix) {
                return false;
            }
        }

        // Compare remaining bits
        if ($remainingBits > 0) {
            $mask = ~((1 << (8 - $remainingBits)) - 1) & 0xFF;
            $ipByte = ord($ipBinary[$fullBytes]);
            $subnetByte = ord($subnetBinary[$fullBytes]);

            if (($ipByte & $mask) !== ($subnetByte & $mask)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns the unique identifier for this strategy.
     *
     * @return string The strategy name 'ip'
     */
    public function getName(): string
    {
        return FlagStrategy::IP->value;
    }
}
