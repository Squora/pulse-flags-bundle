<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Strategy\Validation;

/**
 * Validator for ip strategy configuration.
 */
class IpStrategyValidator implements StrategyValidatorInterface
{
    public function validate(array $config): ValidationResult
    {
        $result = new ValidationResult();

        $hasWhitelistIps = isset($config['whitelist_ips']) && !empty($config['whitelist_ips']);
        $hasIpRanges = isset($config['ip_ranges']) && !empty($config['ip_ranges']);

        // Must have at least one
        if (!$hasWhitelistIps && !$hasIpRanges) {
            $result->addError('IP strategy requires either "whitelist_ips" or "ip_ranges"');
            return $result;
        }

        // Validate whitelist_ips
        if ($hasWhitelistIps) {
            if (!is_array($config['whitelist_ips'])) {
                $result->addError('whitelist_ips must be an array');
            } else {
                foreach ($config['whitelist_ips'] as $index => $ip) {
                    if (!is_string($ip)) {
                        $result->addError(sprintf('whitelist_ips[%d]: IP must be string', $index));
                        continue;
                    }

                    if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
                        $result->addError(sprintf('whitelist_ips[%d]: Invalid IP address "%s"', $index, $ip));
                    }
                }
            }
        }

        // Validate ip_ranges
        if ($hasIpRanges) {
            if (!is_array($config['ip_ranges'])) {
                $result->addError('ip_ranges must be an array');
            } else {
                foreach ($config['ip_ranges'] as $index => $range) {
                    if (!is_string($range)) {
                        $result->addError(sprintf('ip_ranges[%d]: Range must be string', $index));
                        continue;
                    }

                    $this->validateCidrRange($range, $index, $result);
                }
            }
        }

        return $result;
    }

    /**
     * Validate CIDR range format.
     *
     * @param string $range
     * @param int $index
     * @param ValidationResult $result
     * @return void
     */
    private function validateCidrRange(string $range, int $index, ValidationResult $result): void
    {
        // Allow simple IP (treated as /32 or /128)
        if (!str_contains($range, '/')) {
            if (filter_var($range, FILTER_VALIDATE_IP) === false) {
                $result->addError(sprintf('ip_ranges[%d]: Invalid IP address "%s"', $index, $range));
            }
            return;
        }

        [$ip, $bits] = explode('/', $range, 2);

        // Validate IP part
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            $result->addError(sprintf('ip_ranges[%d]: Invalid IP address in CIDR "%s"', $index, $ip));
            return;
        }

        // Validate bits part
        if (!ctype_digit($bits)) {
            $result->addError(sprintf('ip_ranges[%d]: CIDR bits must be numeric in "%s"', $index, $range));
            return;
        }

        $bits = (int) $bits;
        $isIpv6 = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
        $maxBits = $isIpv6 ? 128 : 32;

        if ($bits < 0 || $bits > $maxBits) {
            $result->addError(sprintf(
                'ip_ranges[%d]: CIDR bits must be between 0 and %d for %s in "%s"',
                $index,
                $maxBits,
                $isIpv6 ? 'IPv6' : 'IPv4',
                $range
            ));
        }
    }

    public function getStrategyName(): string
    {
        return 'ip';
    }
}
