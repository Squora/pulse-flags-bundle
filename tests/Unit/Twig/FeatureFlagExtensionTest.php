<?php

declare(strict_types=1);

namespace Pulse\FlagsBundle\Tests\Unit\Twig;

use PHPUnit\Framework\TestCase;
use Pulse\FlagsBundle\Service\PermanentFeatureFlagService;
use Pulse\FlagsBundle\Service\PersistentFeatureFlagService;
use Pulse\FlagsBundle\Twig\FeatureFlagExtension;
use Twig\TwigFunction;

/**
 * Unit tests for FeatureFlagExtension class.
 *
 * Tests Twig extension functionality including:
 * - Function registration (is_feature_enabled, feature_flag_config)
 * - isFeatureEnabled() checks permanent flags first
 * - Fallback to persistent flags when permanent not found
 * - Context propagation for strategy evaluation
 * - Returns false/null when flag doesn't exist
 * - Permanent flag priority over persistent flags
 */
class FeatureFlagExtensionTest extends TestCase
{
    private PermanentFeatureFlagService $permanentService;
    private PersistentFeatureFlagService $persistentService;
    private FeatureFlagExtension $extension;

    protected function setUp(): void
    {
        // Arrange: Create service mocks
        $this->permanentService = $this->createMock(PermanentFeatureFlagService::class);
        $this->persistentService = $this->createMock(PersistentFeatureFlagService::class);

        // Create extension with mocked services
        $this->extension = new FeatureFlagExtension(
            $this->permanentService,
            $this->persistentService
        );
    }

    public function testItRegistersTwigFunctions(): void
    {
        // Arrange: Extension created in setUp

        // Act: Get registered functions
        $functions = $this->extension->getFunctions();

        // Assert: Two functions registered
        $this->assertCount(2, $functions);
        $this->assertContainsOnlyInstancesOf(TwigFunction::class, $functions);

        // Extract function names
        $functionNames = array_map(fn (TwigFunction $f) => $f->getName(), $functions);

        // Assert: Correct function names
        $this->assertContains('is_feature_enabled', $functionNames);
        $this->assertContains('feature_flag_config', $functionNames);
    }

    public function testIsFeatureEnabledReturnsTrueForEnabledPermanentFlag(): void
    {
        // Arrange: Permanent flag exists and is enabled
        $this->permanentService->method('exists')->with('permanent.feature')->willReturn(true);
        $this->permanentService->method('isEnabled')->with('permanent.feature', [])->willReturn(true);

        // Act: Check flag
        $result = $this->extension->isFeatureEnabled('permanent.feature');

        // Assert: Flag is enabled
        $this->assertTrue($result);
    }

    public function testIsFeatureEnabledReturnsFalseForDisabledPermanentFlag(): void
    {
        // Arrange: Permanent flag exists but is disabled
        $this->permanentService->method('exists')->with('permanent.disabled')->willReturn(true);
        $this->permanentService->method('isEnabled')->with('permanent.disabled', [])->willReturn(false);

        // Act: Check flag
        $result = $this->extension->isFeatureEnabled('permanent.disabled');

        // Assert: Flag is disabled
        $this->assertFalse($result);
    }

    public function testIsFeatureEnabledFallbacksToPersistentWhenPermanentNotFound(): void
    {
        // Arrange: Permanent flag doesn't exist, persistent flag is enabled
        $this->permanentService->method('exists')->with('persistent.flag')->willReturn(false);
        $this->persistentService->method('isEnabled')->with('persistent.flag', [])->willReturn(true);

        // Act: Check flag
        $result = $this->extension->isFeatureEnabled('persistent.flag');

        // Assert: Persistent flag is enabled
        $this->assertTrue($result);
    }

    public function testIsFeatureEnabledPropagatesContext(): void
    {
        // Arrange: Context for strategy evaluation
        $context = ['user_id' => 42, 'session_id' => 'abc123'];
        $this->permanentService->method('exists')->with('contextual.flag')->willReturn(false);
        $this->persistentService->expects($this->once())
            ->method('isEnabled')
            ->with('contextual.flag', $context)
            ->willReturn(true);

        // Act: Check flag with context
        $result = $this->extension->isFeatureEnabled('contextual.flag', $context);

        // Assert: Context was passed and flag is enabled
        $this->assertTrue($result);
    }

    public function testIsFeatureEnabledPassesContextToPermanentFlag(): void
    {
        // Arrange: Permanent flag with percentage strategy needs context
        $context = ['user_id' => 123];
        $this->permanentService->method('exists')->with('permanent.percentage')->willReturn(true);
        $this->permanentService->expects($this->once())
            ->method('isEnabled')
            ->with('permanent.percentage', $context)
            ->willReturn(true);

        // Act: Check flag with context
        $result = $this->extension->isFeatureEnabled('permanent.percentage', $context);

        // Assert: Context was passed to permanent service
        $this->assertTrue($result);
    }

    public function testIsFeatureEnabledReturnsFalseWhenFlagNotFound(): void
    {
        // Arrange: Flag doesn't exist in either service
        $this->permanentService->method('exists')->with('nonexistent')->willReturn(false);
        $this->persistentService->method('isEnabled')->with('nonexistent', [])->willReturn(false);

        // Act: Check nonexistent flag
        $result = $this->extension->isFeatureEnabled('nonexistent');

        // Assert: Returns false
        $this->assertFalse($result);
    }

    public function testIsFeatureEnabledPrioritizesPermanentOverPersistent(): void
    {
        // Arrange: Both permanent and persistent flags exist
        $this->permanentService->method('exists')->with('both.flag')->willReturn(true);
        $this->permanentService->method('isEnabled')->with('both.flag', [])->willReturn(false);

        // Persistent service should NOT be called
        $this->persistentService->expects($this->never())->method('isEnabled');

        // Act: Check flag
        $result = $this->extension->isFeatureEnabled('both.flag');

        // Assert: Returns permanent flag's value (false), persistent never checked
        $this->assertFalse($result);
    }

    public function testGetFeatureFlagConfigReturnsPermanentFlagConfig(): void
    {
        // Arrange: Permanent flag exists
        $config = ['enabled' => true, 'strategy' => 'simple', 'description' => 'Test flag'];
        $this->permanentService->method('getConfig')->with('permanent.flag')->willReturn($config);

        // Act: Get config
        $result = $this->extension->getFeatureFlagConfig('permanent.flag');

        // Assert: Config returned
        $this->assertEquals($config, $result);
    }

    public function testGetFeatureFlagConfigFallbacksToPersistent(): void
    {
        // Arrange: Permanent flag doesn't exist, persistent does
        $config = ['enabled' => false, 'strategy' => 'percentage', 'percentage' => 50];
        $this->permanentService->method('getConfig')->with('persistent.flag')->willReturn(null);
        $this->persistentService->method('getConfig')->with('persistent.flag')->willReturn($config);

        // Act: Get config
        $result = $this->extension->getFeatureFlagConfig('persistent.flag');

        // Assert: Persistent config returned
        $this->assertEquals($config, $result);
    }

    public function testGetFeatureFlagConfigReturnsNullWhenNotFound(): void
    {
        // Arrange: Flag doesn't exist in either service
        $this->permanentService->method('getConfig')->with('nonexistent')->willReturn(null);
        $this->persistentService->method('getConfig')->with('nonexistent')->willReturn(null);

        // Act: Get config
        $result = $this->extension->getFeatureFlagConfig('nonexistent');

        // Assert: Returns null
        $this->assertNull($result);
    }

    public function testGetFeatureFlagConfigPrioritizesPermanentOverPersistent(): void
    {
        // Arrange: Both permanent and persistent flags exist
        $permanentConfig = ['enabled' => true, 'strategy' => 'simple'];
        $this->permanentService->method('getConfig')->with('both.flag')->willReturn($permanentConfig);

        // Persistent service should NOT be called
        $this->persistentService->expects($this->never())->method('getConfig');

        // Act: Get config
        $result = $this->extension->getFeatureFlagConfig('both.flag');

        // Assert: Returns permanent config, persistent never checked
        $this->assertEquals($permanentConfig, $result);
    }
}
