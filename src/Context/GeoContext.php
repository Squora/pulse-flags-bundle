<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Context;

/**
 * Context for geographic-based strategy (GeoStrategy).
 */
final class GeoContext implements ContextInterface
{
    public function __construct(
        private readonly string $country,
        private readonly ?string $region = null,
        private readonly ?string $city = null
    ) {
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function getRegion(): ?string
    {
        return $this->region;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function toArray(): array
    {
        $result = ['country' => $this->country];

        if ($this->region !== null) {
            $result['region'] = $this->region;
        }

        if ($this->city !== null) {
            $result['city'] = $this->city;
        }

        return $result;
    }
}
