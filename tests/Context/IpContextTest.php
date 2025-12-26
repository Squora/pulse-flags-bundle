<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Tests\Context;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pulse\Flags\Core\Context\ContextInterface;
use Pulse\Flags\Core\Context\IpContext;

final class IpContextTest extends TestCase
{
    #[Test]
    public function it_can_be_created_with_ip_address(): void
    {
        // Arrange
        $ipAddress = '192.168.1.1';

        // Act
        $context = new IpContext(ipAddress: $ipAddress);

        // Assert
        self::assertInstanceOf(IpContext::class, $context);
        self::assertSame($ipAddress, $context->getIpAddress());
    }

    #[Test]
    public function it_implements_context_interface(): void
    {
        // Arrange
        $context = new IpContext(ipAddress: '127.0.0.1');

        // Act & Assert
        self::assertInstanceOf(ContextInterface::class, $context);
    }

    #[Test]
    public function it_converts_to_array_correctly(): void
    {
        // Arrange
        $ipAddress = '10.0.0.1';
        $context = new IpContext(ipAddress: $ipAddress);

        // Act
        $result = $context->toArray();

        // Assert
        self::assertSame(['ip_address' => $ipAddress], $result);
    }

    #[Test]
    #[DataProvider('provideIpv4Addresses')]
    public function it_handles_various_ipv4_addresses(string $ip): void
    {
        // Arrange & Act
        $context = new IpContext(ipAddress: $ip);

        // Assert
        self::assertSame($ip, $context->getIpAddress());
    }

    public static function provideIpv4Addresses(): iterable
    {
        yield 'localhost' => ['127.0.0.1'];
        yield 'private network 192.168.x.x' => ['192.168.1.100'];
        yield 'private network 10.x.x.x' => ['10.0.0.1'];
        yield 'private network 172.16.x.x' => ['172.16.0.1'];
        yield 'public IP' => ['8.8.8.8'];
        yield 'broadcast address' => ['255.255.255.255'];
        yield 'network address' => ['0.0.0.0'];
        yield 'all zeros except last' => ['0.0.0.1'];
        yield 'all 255 except last' => ['255.255.255.0'];
    }

    #[Test]
    #[DataProvider('provideIpv6Addresses')]
    public function it_handles_various_ipv6_addresses(string $ip): void
    {
        // Arrange & Act
        $context = new IpContext(ipAddress: $ip);

        // Assert
        self::assertSame($ip, $context->getIpAddress());
    }

    public static function provideIpv6Addresses(): iterable
    {
        yield 'full IPv6' => ['2001:0db8:85a3:0000:0000:8a2e:0370:7334'];
        yield 'compressed IPv6' => ['2001:db8:85a3::8a2e:370:7334'];
        yield 'localhost IPv6' => ['::1'];
        yield 'IPv6 any' => ['::'];
        yield 'link-local' => ['fe80::1'];
        yield 'multicast' => ['ff02::1'];
        yield 'IPv4-mapped IPv6' => ['::ffff:192.0.2.1'];
    }

    #[Test]
    public function it_handles_empty_string(): void
    {
        // Arrange
        $ipAddress = '';

        // Act
        $context = new IpContext(ipAddress: $ipAddress);

        // Assert
        self::assertSame('', $context->getIpAddress());
    }

    #[Test]
    public function it_handles_invalid_ip_format(): void
    {
        // Arrange
        $invalidIp = 'not-an-ip';

        // Act
        $context = new IpContext(ipAddress: $invalidIp);

        // Assert - class doesn't validate, just stores
        self::assertSame($invalidIp, $context->getIpAddress());
    }

    #[Test]
    public function it_preserves_whitespace(): void
    {
        // Arrange
        $ipWithSpaces = ' 192.168.1.1 ';

        // Act
        $context = new IpContext(ipAddress: $ipWithSpaces);

        // Assert
        self::assertSame($ipWithSpaces, $context->getIpAddress());
    }

    #[Test]
    public function it_handles_ip_with_port(): void
    {
        // Arrange
        $ipWithPort = '192.168.1.1:8080';

        // Act
        $context = new IpContext(ipAddress: $ipWithPort);

        // Assert
        self::assertSame($ipWithPort, $context->getIpAddress());
    }

    #[Test]
    public function it_handles_ipv6_with_port(): void
    {
        // Arrange
        $ipv6WithPort = '[2001:db8::1]:8080';

        // Act
        $context = new IpContext(ipAddress: $ipv6WithPort);

        // Assert
        self::assertSame($ipv6WithPort, $context->getIpAddress());
    }

    #[Test]
    public function it_returns_consistent_array_on_multiple_calls(): void
    {
        // Arrange
        $context = new IpContext(ipAddress: '10.20.30.40');

        // Act
        $array1 = $context->toArray();
        $array2 = $context->toArray();

        // Assert
        self::assertSame($array1, $array2);
    }

    #[Test]
    public function it_handles_cidr_notation(): void
    {
        // Arrange
        $cidr = '192.168.1.0/24';

        // Act
        $context = new IpContext(ipAddress: $cidr);

        // Assert
        self::assertSame($cidr, $context->getIpAddress());
    }

    #[Test]
    public function it_handles_ipv6_cidr_notation(): void
    {
        // Arrange
        $cidr = '2001:db8::/32';

        // Act
        $context = new IpContext(ipAddress: $cidr);

        // Assert
        self::assertSame($cidr, $context->getIpAddress());
    }

    #[Test]
    public function it_handles_special_ip_addresses(): void
    {
        // Arrange & Act
        $loopback = new IpContext(ipAddress: '127.0.0.1');
        $any = new IpContext(ipAddress: '0.0.0.0');
        $broadcast = new IpContext(ipAddress: '255.255.255.255');

        // Assert
        self::assertSame('127.0.0.1', $loopback->getIpAddress());
        self::assertSame('0.0.0.0', $any->getIpAddress());
        self::assertSame('255.255.255.255', $broadcast->getIpAddress());
    }

    #[Test]
    public function it_handles_numeric_string(): void
    {
        // Arrange
        $numericIp = '3232235777'; // 192.168.1.1 as integer

        // Act
        $context = new IpContext(ipAddress: $numericIp);

        // Assert
        self::assertSame($numericIp, $context->getIpAddress());
    }

    #[Test]
    public function it_handles_hostname_instead_of_ip(): void
    {
        // Arrange
        $hostname = 'example.com';

        // Act
        $context = new IpContext(ipAddress: $hostname);

        // Assert - class doesn't validate, just stores
        self::assertSame($hostname, $context->getIpAddress());
    }

    #[Test]
    public function different_instances_with_same_ip_are_equal(): void
    {
        // Arrange
        $context1 = new IpContext(ipAddress: '192.168.1.1');
        $context2 = new IpContext(ipAddress: '192.168.1.1');

        // Act & Assert
        self::assertEquals($context1->getIpAddress(), $context2->getIpAddress());
        self::assertNotSame($context1, $context2);
    }
}
