<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Tests\Context;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pulse\Flags\Core\Context\ContextInterface;
use Pulse\Flags\Core\Context\GeoContext;

final class GeoContextTest extends TestCase
{
    #[Test]
    public function it_can_be_created_with_country_only(): void
    {
        // Arrange
        $country = 'US';

        // Act
        $context = new GeoContext(country: $country);

        // Assert
        self::assertInstanceOf(GeoContext::class, $context);
        self::assertSame($country, $context->getCountry());
        self::assertNull($context->getRegion());
        self::assertNull($context->getCity());
    }

    #[Test]
    public function it_can_be_created_with_country_and_region(): void
    {
        // Arrange
        $country = 'US';
        $region = 'California';

        // Act
        $context = new GeoContext(
            country: $country,
            region: $region
        );

        // Assert
        self::assertSame($country, $context->getCountry());
        self::assertSame($region, $context->getRegion());
        self::assertNull($context->getCity());
    }

    #[Test]
    public function it_can_be_created_with_all_parameters(): void
    {
        // Arrange
        $country = 'US';
        $region = 'California';
        $city = 'San Francisco';

        // Act
        $context = new GeoContext(
            country: $country,
            region: $region,
            city: $city
        );

        // Assert
        self::assertSame($country, $context->getCountry());
        self::assertSame($region, $context->getRegion());
        self::assertSame($city, $context->getCity());
    }

    #[Test]
    public function it_implements_context_interface(): void
    {
        // Arrange
        $context = new GeoContext(country: 'US');

        // Act & Assert
        self::assertInstanceOf(ContextInterface::class, $context);
    }

    #[Test]
    #[DataProvider('provideToArrayScenarios')]
    public function it_converts_to_array_correctly(
        string $country,
        ?string $region,
        ?string $city,
        array $expectedArray
    ): void {
        // Arrange
        $context = new GeoContext(
            country: $country,
            region: $region,
            city: $city
        );

        // Act
        $result = $context->toArray();

        // Assert
        self::assertSame($expectedArray, $result);
    }

    public static function provideToArrayScenarios(): iterable
    {
        yield 'only country' => [
            'country' => 'US',
            'region' => null,
            'city' => null,
            'expectedArray' => [
                'country' => 'US',
            ],
        ];

        yield 'country and region' => [
            'country' => 'DE',
            'region' => 'Bavaria',
            'city' => null,
            'expectedArray' => [
                'country' => 'DE',
                'region' => 'Bavaria',
            ],
        ];

        yield 'country and city without region' => [
            'country' => 'FR',
            'region' => null,
            'city' => 'Paris',
            'expectedArray' => [
                'country' => 'FR',
                'city' => 'Paris',
            ],
        ];

        yield 'all fields populated' => [
            'country' => 'GB',
            'region' => 'England',
            'city' => 'London',
            'expectedArray' => [
                'country' => 'GB',
                'region' => 'England',
                'city' => 'London',
            ],
        ];
    }

    #[Test]
    #[DataProvider('provideCountryCodes')]
    public function it_handles_various_country_code_formats(string $countryCode): void
    {
        // Arrange & Act
        $context = new GeoContext(country: $countryCode);

        // Assert
        self::assertSame($countryCode, $context->getCountry());
    }

    public static function provideCountryCodes(): iterable
    {
        yield 'ISO 3166-1 alpha-2' => ['US'];
        yield 'ISO 3166-1 alpha-3' => ['USA'];
        yield 'lowercase code' => ['us'];
        yield 'mixed case' => ['Us'];
        yield 'numeric code' => ['840'];
    }

    #[Test]
    public function it_handles_unicode_characters_in_city_names(): void
    {
        // Arrange
        $country = 'RU';
        $region = 'Москва';
        $city = 'Москва';

        // Act
        $context = new GeoContext(
            country: $country,
            region: $region,
            city: $city
        );

        // Assert
        self::assertSame($region, $context->getRegion());
        self::assertSame($city, $context->getCity());
    }

    #[Test]
    public function it_handles_special_characters_in_location_names(): void
    {
        // Arrange
        $country = 'FR';
        $region = "Île-de-France";
        $city = "Saint-Étienne";

        // Act
        $context = new GeoContext(
            country: $country,
            region: $region,
            city: $city
        );

        // Assert
        self::assertSame($region, $context->getRegion());
        self::assertSame($city, $context->getCity());
    }

    #[Test]
    public function it_handles_long_location_names(): void
    {
        // Arrange
        $country = 'NZ';
        $city = 'Taumatawhakatangihangakoauauotamateaturipukakapikimaungahoronukupokaiwhenuakitanatahu';

        // Act
        $context = new GeoContext(
            country: $country,
            city: $city
        );

        // Assert
        self::assertSame($city, $context->getCity());
        self::assertSame(85, strlen($context->getCity()));
    }

    #[Test]
    public function it_handles_empty_string_as_country(): void
    {
        // Arrange
        $country = '';

        // Act
        $context = new GeoContext(country: $country);

        // Assert
        self::assertSame('', $context->getCountry());
    }

    #[Test]
    public function it_preserves_whitespace_in_values(): void
    {
        // Arrange
        $country = ' US ';
        $region = '  California  ';
        $city = ' San Francisco ';

        // Act
        $context = new GeoContext(
            country: $country,
            region: $region,
            city: $city
        );

        // Assert
        self::assertSame($country, $context->getCountry());
        self::assertSame($region, $context->getRegion());
        self::assertSame($city, $context->getCity());
    }

    #[Test]
    public function it_handles_real_world_locations(): void
    {
        // Arrange & Act
        $context1 = new GeoContext(
            country: 'JP',
            region: '東京都',
            city: '東京'
        );

        $context2 = new GeoContext(
            country: 'CN',
            region: '北京市',
            city: '北京'
        );

        $context3 = new GeoContext(
            country: 'AE',
            region: 'Dubai',
            city: 'Dubai'
        );

        // Assert
        self::assertSame('東京', $context1->getCity());
        self::assertSame('北京', $context2->getCity());
        self::assertSame('Dubai', $context3->getCity());
    }

    #[Test]
    public function it_returns_consistent_array_on_multiple_calls(): void
    {
        // Arrange
        $context = new GeoContext(
            country: 'US',
            region: 'California',
            city: 'Los Angeles'
        );

        // Act
        $array1 = $context->toArray();
        $array2 = $context->toArray();

        // Assert
        self::assertSame($array1, $array2);
    }

    #[Test]
    public function it_handles_continent_names_as_regions(): void
    {
        // Arrange
        $context = new GeoContext(
            country: 'World',
            region: 'Europe'
        );

        // Act & Assert
        self::assertSame('World', $context->getCountry());
        self::assertSame('Europe', $context->getRegion());
    }
}
