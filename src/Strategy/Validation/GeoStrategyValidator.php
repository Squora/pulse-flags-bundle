<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Strategy\Validation;

/**
 * Validator for geo strategy configuration.
 */
class GeoStrategyValidator implements StrategyValidatorInterface
{
    public function validate(array $config): ValidationResult
    {
        $result = new ValidationResult();

        $hasCountries = isset($config['countries']) && !empty($config['countries']);
        $hasRegions = isset($config['regions']) && !empty($config['regions']);
        $hasCities = isset($config['cities']) && !empty($config['cities']);

        // Must have at least one
        if (!$hasCountries && !$hasRegions && !$hasCities) {
            $result->addError('Geo strategy requires at least one of: "countries", "regions", "cities"');
            return $result;
        }

        // Validate countries
        if ($hasCountries) {
            if (!is_array($config['countries'])) {
                $result->addError('countries must be an array');
            } else {
                foreach ($config['countries'] as $index => $country) {
                    if (!is_string($country)) {
                        $result->addError(sprintf('countries[%d]: Country code must be string', $index));
                        continue;
                    }

                    // Validate ISO 3166-1 alpha-2 format (2 letters)
                    if (strlen($country) !== 2 || !ctype_alpha($country)) {
                        $result->addWarning(sprintf(
                            'countries[%d]: "%s" should be ISO 3166-1 alpha-2 code (2 letters)',
                            $index,
                            $country
                        ));
                    }
                }
            }
        }

        // Validate regions
        if ($hasRegions) {
            if (!is_array($config['regions'])) {
                $result->addError('regions must be an array');
            } else {
                foreach ($config['regions'] as $index => $region) {
                    if (!is_string($region)) {
                        $result->addError(sprintf('regions[%d]: Region must be string', $index));
                    }
                }
            }
        }

        // Validate cities
        if ($hasCities) {
            if (!is_array($config['cities'])) {
                $result->addError('cities must be an array');
            } else {
                foreach ($config['cities'] as $index => $city) {
                    if (!is_string($city)) {
                        $result->addError(sprintf('cities[%d]: City must be string', $index));
                    }
                }
            }
        }

        return $result;
    }

    public function getStrategyName(): string
    {
        return 'geo';
    }
}
