<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Tests\Strategy;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pulse\Flags\Core\Enum\FlagStrategy;
use Pulse\Flags\Core\Strategy\IpStrategy;
use Pulse\Flags\Core\Strategy\StrategyInterface;

final class IpStrategyTest extends TestCase
{
    #[Test]
    public function it_implements_strategy_interface(): void
    {
        // Arrange
        $strategy = new IpStrategy();

        // Act & Assert
        self::assertInstanceOf(StrategyInterface::class, $strategy);
    }

    #[Test]
    public function it_returns_correct_strategy_name(): void
    {
        // Arrange
        $strategy = new IpStrategy();

        // Act
        $name = $strategy->getName();

        // Assert
        self::assertSame('ip', $name);
        self::assertSame(FlagStrategy::IP->value, $name);
    }

    #[Test]
    public function it_is_enabled_when_ip_matches_whitelist(): void
    {
        // Arrange
        $strategy = new IpStrategy();
        $config = ['whitelist_ips' => ['192.168.1.100']];
        $context = ['ip_address' => '192.168.1.100'];

        // Act
        $result = $strategy->isEnabled($config, $context);

        // Assert
        self::assertTrue($result);
    }

    #[Test]
    public function it_is_disabled_when_ip_does_not_match_whitelist(): void
    {
        // Arrange
        $strategy = new IpStrategy();
        $config = ['whitelist_ips' => ['192.168.1.100']];
        $context = ['ip_address' => '192.168.1.101'];

        // Act
        $result = $strategy->isEnabled($config, $context);

        // Assert
        self::assertFalse($result);
    }

    #[Test]
    public function it_matches_multiple_whitelist_ips(): void
    {
        // Arrange
        $strategy = new IpStrategy();
        $config = ['whitelist_ips' => ['192.168.1.100', '192.168.1.101', '10.0.0.5']];

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, ['ip_address' => '192.168.1.100']));
        self::assertTrue($strategy->isEnabled($config, ['ip_address' => '192.168.1.101']));
        self::assertTrue($strategy->isEnabled($config, ['ip_address' => '10.0.0.5']));
        self::assertFalse($strategy->isEnabled($config, ['ip_address' => '192.168.1.102']));
    }

    #[Test]
    public function it_returns_false_when_ip_address_is_missing(): void
    {
        // Arrange
        $strategy = new IpStrategy();
        $config = ['whitelist_ips' => ['192.168.1.100']];

        // Act & Assert
        self::assertFalse($strategy->isEnabled($config, context: []));
        self::assertFalse($strategy->isEnabled($config, ['ip_address' => null]));
    }

    #[Test]
    public function it_returns_false_when_ip_address_is_not_string(): void
    {
        // Arrange
        $strategy = new IpStrategy();
        $config = ['whitelist_ips' => ['192.168.1.100']];

        // Act & Assert
        self::assertFalse($strategy->isEnabled($config, ['ip_address' => 123]));
        self::assertFalse($strategy->isEnabled($config, ['ip_address' => ['192.168.1.100']]));
    }

    #[Test]
    public function it_returns_false_when_no_whitelist_or_ranges_configured(): void
    {
        // Arrange
        $strategy = new IpStrategy();
        $config = []; // Empty config

        // Act
        $result = $strategy->isEnabled($config, ['ip_address' => '192.168.1.100']);

        // Assert
        self::assertFalse($result);
    }

    #[Test]
    public function it_matches_ipv4_cidr_range(): void
    {
        // Arrange
        $strategy = new IpStrategy();
        $config = ['ip_ranges' => ['192.168.1.0/24']]; // 192.168.1.0 - 192.168.1.255

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, ['ip_address' => '192.168.1.1']));
        self::assertTrue($strategy->isEnabled($config, ['ip_address' => '192.168.1.100']));
        self::assertTrue($strategy->isEnabled($config, ['ip_address' => '192.168.1.255']));
        self::assertFalse($strategy->isEnabled($config, ['ip_address' => '192.168.2.1']));
        self::assertFalse($strategy->isEnabled($config, ['ip_address' => '192.168.0.1']));
    }

    #[Test]
    public function it_matches_private_network_ranges(): void
    {
        // Arrange
        $strategy = new IpStrategy();
        $config = ['ip_ranges' => [
            '10.0.0.0/8',      // 10.0.0.0 - 10.255.255.255
            '192.168.0.0/16',  // 192.168.0.0 - 192.168.255.255
            '172.16.0.0/12',   // 172.16.0.0 - 172.31.255.255
        ]];

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, ['ip_address' => '10.0.0.1']));
        self::assertTrue($strategy->isEnabled($config, ['ip_address' => '10.50.100.200']));
        self::assertTrue($strategy->isEnabled($config, ['ip_address' => '192.168.1.1']));
        self::assertTrue($strategy->isEnabled($config, ['ip_address' => '192.168.255.255']));
        self::assertTrue($strategy->isEnabled($config, ['ip_address' => '172.16.0.1']));
        self::assertTrue($strategy->isEnabled($config, ['ip_address' => '172.31.255.255']));
        self::assertFalse($strategy->isEnabled($config, ['ip_address' => '8.8.8.8'])); // Public Google DNS
    }

    #[Test]
    public function it_matches_ipv6_addresses_in_whitelist(): void
    {
        // Arrange
        $strategy = new IpStrategy();
        $config = ['whitelist_ips' => [
            '2001:0db8:85a3:0000:0000:8a2e:0370:7334',
            '::1', // localhost
        ]];

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, ['ip_address' => '2001:0db8:85a3:0000:0000:8a2e:0370:7334']));
        self::assertTrue($strategy->isEnabled($config, ['ip_address' => '::1']));
        self::assertFalse($strategy->isEnabled($config, ['ip_address' => '2001:0db8:85a3:0000:0000:8a2e:0370:7335']));
    }

    #[Test]
    public function it_matches_ipv6_cidr_range(): void
    {
        // Arrange
        $strategy = new IpStrategy();
        $config = ['ip_ranges' => ['2001:db8::/32']];

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, ['ip_address' => '2001:db8::1']));
        self::assertTrue($strategy->isEnabled($config, ['ip_address' => '2001:db8:1234:5678::1']));
        self::assertFalse($strategy->isEnabled($config, ['ip_address' => '2001:db9::1']));
    }

    #[Test]
    public function it_combines_whitelist_and_ranges(): void
    {
        // Arrange - office IP + VPN range
        $strategy = new IpStrategy();
        $config = [
            'whitelist_ips' => ['203.0.113.42'], // Specific office IP
            'ip_ranges' => ['10.8.0.0/24'],      // VPN range
        ];

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, ['ip_address' => '203.0.113.42'])); // Office
        self::assertTrue($strategy->isEnabled($config, ['ip_address' => '10.8.0.1']));     // VPN
        self::assertTrue($strategy->isEnabled($config, ['ip_address' => '10.8.0.100']));   // VPN
        self::assertFalse($strategy->isEnabled($config, ['ip_address' => '8.8.8.8']));     // Public
    }

    #[Test]
    public function it_handles_multiple_cidr_ranges(): void
    {
        // Arrange
        $strategy = new IpStrategy();
        $config = ['ip_ranges' => [
            '192.168.1.0/24',
            '192.168.2.0/24',
            '10.0.0.0/16',
        ]];

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, ['ip_address' => '192.168.1.50']));
        self::assertTrue($strategy->isEnabled($config, ['ip_address' => '192.168.2.50']));
        self::assertTrue($strategy->isEnabled($config, ['ip_address' => '10.0.50.100']));
        self::assertFalse($strategy->isEnabled($config, ['ip_address' => '192.168.3.50']));
    }

    #[Test]
    public function it_handles_narrow_cidr_ranges(): void
    {
        // Arrange - /32 is a single IP
        $strategy = new IpStrategy();
        $config = ['ip_ranges' => ['192.168.1.100/32']];

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, ['ip_address' => '192.168.1.100']));
        self::assertFalse($strategy->isEnabled($config, ['ip_address' => '192.168.1.101']));
    }

    #[Test]
    public function it_handles_wide_cidr_ranges(): void
    {
        // Arrange - /16 is a large range
        $strategy = new IpStrategy();
        $config = ['ip_ranges' => ['192.168.0.0/16']];

        // Act & Assert - all 192.168.x.x addresses
        self::assertTrue($strategy->isEnabled($config, ['ip_address' => '192.168.0.1']));
        self::assertTrue($strategy->isEnabled($config, ['ip_address' => '192.168.100.200']));
        self::assertTrue($strategy->isEnabled($config, ['ip_address' => '192.168.255.255']));
        self::assertFalse($strategy->isEnabled($config, ['ip_address' => '192.167.255.255']));
        self::assertFalse($strategy->isEnabled($config, ['ip_address' => '192.169.0.1']));
    }

    #[Test]
    public function it_handles_invalid_ip_address(): void
    {
        // Arrange
        $strategy = new IpStrategy();
        $config = ['whitelist_ips' => ['192.168.1.100']];

        // Act & Assert
        self::assertFalse($strategy->isEnabled($config, ['ip_address' => 'not-an-ip']));
        self::assertFalse($strategy->isEnabled($config, ['ip_address' => '999.999.999.999']));
        self::assertFalse($strategy->isEnabled($config, ['ip_address' => '192.168.1'])); // Incomplete
    }

    #[Test]
    public function it_handles_invalid_cidr_notation(): void
    {
        // Arrange
        $strategy = new IpStrategy();
        $config = ['ip_ranges' => ['192.168.1.0/99']]; // Invalid: bits > 32

        // Act
        $result = $strategy->isEnabled($config, ['ip_address' => '192.168.1.100']);

        // Assert
        self::assertFalse($result);
    }

    #[Test]
    public function it_treats_range_without_slash_as_exact_match(): void
    {
        // Arrange
        $strategy = new IpStrategy();
        $config = ['ip_ranges' => ['192.168.1.100']]; // No slash, exact match

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, ['ip_address' => '192.168.1.100']));
        self::assertFalse($strategy->isEnabled($config, ['ip_address' => '192.168.1.101']));
    }

    #[Test]
    public function it_does_not_match_ipv4_with_ipv6_range(): void
    {
        // Arrange
        $strategy = new IpStrategy();
        $config = ['ip_ranges' => ['2001:db8::/32']]; // IPv6 range

        // Act
        $result = $strategy->isEnabled($config, ['ip_address' => '192.168.1.100']); // IPv4

        // Assert - different IP versions don't match
        self::assertFalse($result);
    }

    #[Test]
    public function it_does_not_match_ipv6_with_ipv4_range(): void
    {
        // Arrange
        $strategy = new IpStrategy();
        $config = ['ip_ranges' => ['192.168.0.0/16']]; // IPv4 range

        // Act
        $result = $strategy->isEnabled($config, ['ip_address' => '2001:db8::1']); // IPv6

        // Assert - different IP versions don't match
        self::assertFalse($result);
    }

    #[Test]
    public function it_works_with_office_vpn_scenario(): void
    {
        // Arrange - Enable for office IPs and VPN
        $strategy = new IpStrategy();
        $config = [
            'whitelist_ips' => ['203.0.113.42'], // Office public IP
            'ip_ranges' => ['10.8.0.0/24'],      // VPN subnet
        ];

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, ['ip_address' => '203.0.113.42'])); // From office
        self::assertTrue($strategy->isEnabled($config, ['ip_address' => '10.8.0.50']));    // From VPN
        self::assertFalse($strategy->isEnabled($config, ['ip_address' => '8.8.8.8']));     // From internet
    }

    #[Test]
    public function it_works_with_internal_testing_scenario(): void
    {
        // Arrange - Enable for internal networks only
        $strategy = new IpStrategy();
        $config = ['ip_ranges' => [
            '192.168.0.0/16', // Local network
            '10.0.0.0/8',     // Corporate network
        ]];

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, ['ip_address' => '192.168.1.100']));
        self::assertTrue($strategy->isEnabled($config, ['ip_address' => '10.50.100.200']));
        self::assertFalse($strategy->isEnabled($config, ['ip_address' => '203.0.113.42'])); // External
    }

    #[Test]
    public function it_works_with_staging_access_scenario(): void
    {
        // Arrange - Specific developer IPs
        $strategy = new IpStrategy();
        $config = ['whitelist_ips' => [
            '203.0.113.10', // Developer 1
            '203.0.113.11', // Developer 2
            '203.0.113.12', // Developer 3
        ]];

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, ['ip_address' => '203.0.113.10']));
        self::assertTrue($strategy->isEnabled($config, ['ip_address' => '203.0.113.11']));
        self::assertFalse($strategy->isEnabled($config, ['ip_address' => '203.0.113.99']));
    }

    #[Test]
    public function it_handles_localhost_addresses(): void
    {
        // Arrange
        $strategy = new IpStrategy();
        $config = ['whitelist_ips' => ['127.0.0.1', '::1']];

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, ['ip_address' => '127.0.0.1'])); // IPv4 localhost
        self::assertTrue($strategy->isEnabled($config, ['ip_address' => '::1']));       // IPv6 localhost
    }

    #[Test]
    public function it_handles_context_with_additional_fields(): void
    {
        // Arrange
        $strategy = new IpStrategy();
        $config = ['whitelist_ips' => ['192.168.1.100']];
        $context = [
            'ip_address' => '192.168.1.100',
            'user_id' => 'user-123',
            'country' => 'US',
        ];

        // Act
        $result = $strategy->isEnabled($config, $context);

        // Assert - ignores other context fields
        self::assertTrue($result);
    }
}
