<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Tests\Strategy;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pulse\Flags\Core\Enum\FlagStrategy;
use Pulse\Flags\Core\Strategy\GeoStrategy;
use Pulse\Flags\Core\Strategy\StrategyInterface;

final class GeoStrategyTest extends TestCase
{
    #[Test]
    public function it_implements_strategy_interface(): void
    {
        // Arrange
        $strategy = new GeoStrategy();

        // Act & Assert
        self::assertInstanceOf(StrategyInterface::class, $strategy);
    }

    #[Test]
    public function it_returns_correct_strategy_name(): void
    {
        // Arrange
        $strategy = new GeoStrategy();

        // Act
        $name = $strategy->getName();

        // Assert
        self::assertSame('geo', $name);
        self::assertSame(FlagStrategy::GEO->value, $name);
    }

    #[Test]
    public function it_is_enabled_when_user_country_matches_configured_country(): void
    {
        // Arrange
        $strategy = new GeoStrategy();
        $config = ['countries' => ['US']];
        $context = ['country' => 'US'];

        // Act
        $result = $strategy->isEnabled($config, $context);

        // Assert
        self::assertTrue($result);
    }

    #[Test]
    public function it_is_disabled_when_user_country_does_not_match(): void
    {
        // Arrange
        $strategy = new GeoStrategy();
        $config = ['countries' => ['US']];
        $context = ['country' => 'CA'];

        // Act
        $result = $strategy->isEnabled($config, $context);

        // Assert
        self::assertFalse($result);
    }

    #[Test]
    public function it_matches_multiple_countries(): void
    {
        // Arrange
        $strategy = new GeoStrategy();
        $config = ['countries' => ['US', 'CA', 'GB', 'AU']];

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, ['country' => 'US']));
        self::assertTrue($strategy->isEnabled($config, ['country' => 'CA']));
        self::assertTrue($strategy->isEnabled($config, ['country' => 'GB']));
        self::assertTrue($strategy->isEnabled($config, ['country' => 'AU']));
        self::assertFalse($strategy->isEnabled($config, ['country' => 'FR']));
    }

    #[Test]
    public function it_is_case_insensitive_for_country_codes(): void
    {
        // Arrange
        $strategy = new GeoStrategy();
        $config = ['countries' => ['us', 'gb']]; // lowercase in config

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, ['country' => 'US'])); // uppercase context
        self::assertTrue($strategy->isEnabled($config, ['country' => 'us'])); // lowercase context
        self::assertTrue($strategy->isEnabled($config, ['country' => 'Us'])); // mixed case context
    }

    #[Test]
    public function it_is_disabled_when_country_is_missing_in_context(): void
    {
        // Arrange
        $strategy = new GeoStrategy();
        $config = ['countries' => ['US']];

        // Act & Assert
        self::assertFalse($strategy->isEnabled($config, context: []));
        self::assertFalse($strategy->isEnabled($config, ['country' => null]));
    }

    #[Test]
    public function it_matches_regions(): void
    {
        // Arrange
        $strategy = new GeoStrategy();
        $config = ['regions' => ['CA', 'NY', 'TX']]; // US states

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, ['region' => 'CA']));
        self::assertTrue($strategy->isEnabled($config, ['region' => 'NY']));
        self::assertTrue($strategy->isEnabled($config, ['region' => 'TX']));
        self::assertFalse($strategy->isEnabled($config, ['region' => 'FL']));
    }

    #[Test]
    public function it_is_case_insensitive_for_regions(): void
    {
        // Arrange
        $strategy = new GeoStrategy();
        $config = ['regions' => ['ca', 'ny']]; // lowercase

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, ['region' => 'CA'])); // uppercase
        self::assertTrue($strategy->isEnabled($config, ['region' => 'ca'])); // lowercase
    }

    #[Test]
    public function it_is_disabled_when_region_is_missing_in_context(): void
    {
        // Arrange
        $strategy = new GeoStrategy();
        $config = ['regions' => ['CA']];

        // Act & Assert
        self::assertFalse($strategy->isEnabled($config, context: []));
        self::assertFalse($strategy->isEnabled($config, ['region' => null]));
    }

    #[Test]
    public function it_matches_cities(): void
    {
        // Arrange
        $strategy = new GeoStrategy();
        $config = ['cities' => ['New York', 'Los Angeles', 'Chicago']];

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, ['city' => 'New York']));
        self::assertTrue($strategy->isEnabled($config, ['city' => 'Los Angeles']));
        self::assertTrue($strategy->isEnabled($config, ['city' => 'Chicago']));
        self::assertFalse($strategy->isEnabled($config, ['city' => 'Miami']));
    }

    #[Test]
    public function it_is_case_insensitive_for_cities(): void
    {
        // Arrange
        $strategy = new GeoStrategy();
        $config = ['cities' => ['New York', 'Los Angeles']];

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, ['city' => 'new york'])); // lowercase
        self::assertTrue($strategy->isEnabled($config, ['city' => 'NEW YORK'])); // uppercase
        self::assertTrue($strategy->isEnabled($config, ['city' => 'NeW YoRk'])); // mixed case
    }

    #[Test]
    public function it_is_disabled_when_city_is_missing_in_context(): void
    {
        // Arrange
        $strategy = new GeoStrategy();
        $config = ['cities' => ['New York']];

        // Act & Assert
        self::assertFalse($strategy->isEnabled($config, context: []));
        self::assertFalse($strategy->isEnabled($config, ['city' => null]));
    }

    #[Test]
    public function it_uses_and_logic_for_country_and_region(): void
    {
        // Arrange
        $strategy = new GeoStrategy();
        $config = [
            'countries' => ['US'],
            'regions' => ['CA', 'NY'],
        ];

        // Act & Assert - both must match
        self::assertTrue($strategy->isEnabled($config, ['country' => 'US', 'region' => 'CA']));
        self::assertTrue($strategy->isEnabled($config, ['country' => 'US', 'region' => 'NY']));
        self::assertFalse($strategy->isEnabled($config, ['country' => 'US', 'region' => 'TX'])); // Wrong region
        self::assertFalse($strategy->isEnabled($config, ['country' => 'CA', 'region' => 'CA'])); // Wrong country
    }

    #[Test]
    public function it_uses_and_logic_for_country_and_city(): void
    {
        // Arrange
        $strategy = new GeoStrategy();
        $config = [
            'countries' => ['US'],
            'cities' => ['New York', 'Los Angeles'],
        ];

        // Act & Assert - both must match
        self::assertTrue($strategy->isEnabled($config, ['country' => 'US', 'city' => 'New York']));
        self::assertTrue($strategy->isEnabled($config, ['country' => 'US', 'city' => 'Los Angeles']));
        self::assertFalse($strategy->isEnabled($config, ['country' => 'US', 'city' => 'Miami'])); // Wrong city
        self::assertFalse($strategy->isEnabled($config, ['country' => 'GB', 'city' => 'New York'])); // Wrong country
    }

    #[Test]
    public function it_uses_and_logic_for_region_and_city(): void
    {
        // Arrange
        $strategy = new GeoStrategy();
        $config = [
            'regions' => ['CA'],
            'cities' => ['San Francisco', 'Los Angeles'],
        ];

        // Act & Assert - both must match
        self::assertTrue($strategy->isEnabled($config, ['region' => 'CA', 'city' => 'San Francisco']));
        self::assertTrue($strategy->isEnabled($config, ['region' => 'CA', 'city' => 'Los Angeles']));
        self::assertFalse($strategy->isEnabled($config, ['region' => 'CA', 'city' => 'Miami'])); // Wrong city
        self::assertFalse($strategy->isEnabled($config, ['region' => 'NY', 'city' => 'San Francisco'])); // Wrong region
    }

    #[Test]
    public function it_uses_and_logic_for_all_three_locations(): void
    {
        // Arrange
        $strategy = new GeoStrategy();
        $config = [
            'countries' => ['US'],
            'regions' => ['CA'],
            'cities' => ['San Francisco', 'Los Angeles'],
        ];

        // Act & Assert - all must match
        self::assertTrue($strategy->isEnabled($config, [
            'country' => 'US',
            'region' => 'CA',
            'city' => 'San Francisco',
        ]));
        self::assertFalse($strategy->isEnabled($config, [
            'country' => 'US',
            'region' => 'CA',
            'city' => 'Miami', // Wrong city
        ]));
        self::assertFalse($strategy->isEnabled($config, [
            'country' => 'US',
            'region' => 'NY', // Wrong region
            'city' => 'San Francisco',
        ]));
        self::assertFalse($strategy->isEnabled($config, [
            'country' => 'CA', // Wrong country
            'region' => 'CA',
            'city' => 'San Francisco',
        ]));
    }

    #[Test]
    public function it_returns_false_when_no_location_criteria_configured(): void
    {
        // Arrange
        $strategy = new GeoStrategy();
        $config = []; // Empty config

        // Act
        $result = $strategy->isEnabled($config, ['country' => 'US']);

        // Assert
        self::assertFalse($result);
    }

    #[Test]
    public function it_works_with_eu_countries_for_gdpr(): void
    {
        // Arrange - EU member states
        $strategy = new GeoStrategy();
        $euCountries = [
            'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
            'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
            'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE',
        ];
        $config = ['countries' => $euCountries];

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, ['country' => 'DE'])); // Germany
        self::assertTrue($strategy->isEnabled($config, ['country' => 'FR'])); // France
        self::assertTrue($strategy->isEnabled($config, ['country' => 'ES'])); // Spain
        self::assertFalse($strategy->isEnabled($config, ['country' => 'US'])); // Not EU
        self::assertFalse($strategy->isEnabled($config, ['country' => 'GB'])); // Post-Brexit
    }

    #[Test]
    public function it_works_with_north_american_rollout(): void
    {
        // Arrange
        $strategy = new GeoStrategy();
        $config = ['countries' => ['US', 'CA', 'MX']];

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, ['country' => 'US']));
        self::assertTrue($strategy->isEnabled($config, ['country' => 'CA']));
        self::assertTrue($strategy->isEnabled($config, ['country' => 'MX']));
        self::assertFalse($strategy->isEnabled($config, ['country' => 'BR'])); // South America
    }

    #[Test]
    public function it_works_with_us_state_specific_feature(): void
    {
        // Arrange - California privacy law
        $strategy = new GeoStrategy();
        $config = [
            'countries' => ['US'],
            'regions' => ['CA'],
        ];

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, ['country' => 'US', 'region' => 'CA']));
        self::assertFalse($strategy->isEnabled($config, ['country' => 'US', 'region' => 'NY']));
        self::assertFalse($strategy->isEnabled($config, ['country' => 'CA', 'region' => 'CA'])); // Canada, not US
    }

    #[Test]
    public function it_works_with_city_specific_beta_test(): void
    {
        // Arrange - Beta test in major cities
        $strategy = new GeoStrategy();
        $config = ['cities' => ['New York', 'San Francisco', 'London', 'Tokyo']];

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, ['city' => 'New York']));
        self::assertTrue($strategy->isEnabled($config, ['city' => 'London']));
        self::assertTrue($strategy->isEnabled($config, ['city' => 'Tokyo']));
        self::assertFalse($strategy->isEnabled($config, ['city' => 'Seattle']));
    }

    #[Test]
    public function it_handles_context_with_additional_fields(): void
    {
        // Arrange
        $strategy = new GeoStrategy();
        $config = ['countries' => ['US']];
        $context = [
            'country' => 'US',
            'user_id' => 'user-123',
            'session_id' => 'sess-abc',
            'ip' => '192.168.1.1',
        ];

        // Act
        $result = $strategy->isEnabled($config, $context);

        // Assert - ignores non-geo fields
        self::assertTrue($result);
    }

    #[Test]
    public function it_handles_empty_country_list(): void
    {
        // Arrange
        $strategy = new GeoStrategy();
        $config = ['countries' => []];

        // Act
        $result = $strategy->isEnabled($config, ['country' => 'US']);

        // Assert - empty list means no restrictions
        self::assertFalse($result);
    }

    #[Test]
    public function it_handles_single_country(): void
    {
        // Arrange
        $strategy = new GeoStrategy();
        $config = ['countries' => ['JP']]; // Japan only

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, ['country' => 'JP']));
        self::assertFalse($strategy->isEnabled($config, ['country' => 'CN']));
        self::assertFalse($strategy->isEnabled($config, ['country' => 'KR']));
    }

    #[Test]
    public function it_handles_numeric_region_codes(): void
    {
        // Arrange
        $strategy = new GeoStrategy();
        $config = ['regions' => ['01', '02', '03']]; // Numeric region codes

        // Act & Assert
        self::assertTrue($strategy->isEnabled($config, ['region' => '01']));
        self::assertTrue($strategy->isEnabled($config, ['region' => '02']));
        self::assertFalse($strategy->isEnabled($config, ['region' => '99']));
    }
}
