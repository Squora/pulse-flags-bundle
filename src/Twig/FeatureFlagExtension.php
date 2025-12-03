<?php

declare(strict_types=1);

namespace Pulse\FlagsBundle\Twig;

use Pulse\FlagsBundle\Service\PermanentFeatureFlagService;
use Pulse\FlagsBundle\Service\PersistentFeatureFlagService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension for feature flag access in templates.
 *
 * Provides Twig functions for checking feature flag status and retrieving
 * flag configuration directly in templates. Automatically checks both
 * permanent and persistent flags.
 *
 * Available functions:
 * - `is_feature_enabled(name, context)` - Check if flag is enabled
 * - `feature_flag_config(name)` - Get flag configuration
 *
 * Usage in templates:
 * ```twig
 * {% if is_feature_enabled('my_feature') %}
 *     <div class="new-feature">...</div>
 * {% endif %}
 *
 * {# With context for percentage/user strategies #}
 * {% if is_feature_enabled('beta_feature', {'user_id': app.user.id}) %}
 *     <p>Beta feature content</p>
 * {% endif %}
 *
 * {# Access configuration #}
 * {% set config = feature_flag_config('my_feature') %}
 * {% if config %}
 *     Strategy: {{ config.strategy }}
 * {% endif %}
 * ```
 */
class FeatureFlagExtension extends AbstractExtension
{
    /**
     * @param PermanentFeatureFlagService $permanentFlagService Service for permanent flags
     * @param PersistentFeatureFlagService $persistentFlagService Service for persistent flags
     */
    public function __construct(
        private PermanentFeatureFlagService $permanentFlagService,
        private PersistentFeatureFlagService $persistentFlagService,
    ) {
    }

    /**
     * Registers Twig functions provided by this extension.
     *
     * @return array<TwigFunction> Array of Twig functions
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('is_feature_enabled', [$this, 'isFeatureEnabled']),
            new TwigFunction('feature_flag_config', [$this, 'getFeatureFlagConfig']),
        ];
    }

    /**
     * Checks if a feature flag is enabled.
     *
     * Searches permanent flags first, then persistent flags. Uses strategy
     * evaluation with provided context.
     *
     * @param string $name Feature flag name
     * @param array<string, mixed> $context Optional context for strategy evaluation (user_id, session_id, etc.)
     * @return bool True if the flag exists and is enabled for the given context
     */
    public function isFeatureEnabled(string $name, array $context = []): bool
    {
        // Check permanent flags first
        if ($this->permanentFlagService->exists($name)) {
            return $this->permanentFlagService->isEnabled($name, $context);
        }

        // Check persistent flags
        return $this->persistentFlagService->isEnabled($name, $context);
    }

    /**
     * Retrieves the configuration for a feature flag.
     *
     * Searches permanent flags first, then persistent flags.
     *
     * @param string $name Feature flag name
     * @return array<string, mixed>|null Flag configuration or null if not found
     */
    public function getFeatureFlagConfig(string $name): ?array
    {
        // Check permanent flags first
        $config = $this->permanentFlagService->getConfig($name);
        if ($config !== null) {
            return $config;
        }

        // Check persistent flags
        return $this->persistentFlagService->getConfig($name);
    }
}
