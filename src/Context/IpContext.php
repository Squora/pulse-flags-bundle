<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Context;

/**
 * Context for IP-based strategy (IpStrategy).
 */
final class IpContext implements ContextInterface
{
    public function __construct(
        private readonly string $ipAddress
    ) {
    }

    public function getIpAddress(): string
    {
        return $this->ipAddress;
    }

    public function toArray(): array
    {
        return [
            'ip_address' => $this->ipAddress,
        ];
    }
}
