<?php

declare(strict_types=1);

namespace Pulse\FlagsBundle\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Pulse\FlagsBundle\Service\PermanentFeatureFlagService;
use Pulse\FlagsBundle\Strategy\PercentageStrategy;

class PermanentFeatureFlagServiceTest extends TestCase
{
    private PermanentFeatureFlagService $service;

    protected function setUp(): void
    {
        $permanentFlags = [
            'permanent.enabled' => ['enabled' => true, 'strategy' => 'simple'],
            'permanent.disabled' => ['enabled' => false, 'strategy' => 'simple'],
            'permanent.percentage' => ['enabled' => true, 'strategy' => 'percentage', 'percentage' => 100],
        ];

        $this->service = new PermanentFeatureFlagService(
            $permanentFlags,
            new NullLogger()
        );
    }

    public function testIsEnabledWithSimpleStrategy(): void
    {
        $this->assertTrue($this->service->isEnabled('permanent.enabled'));
    }

    public function testIsDisabledWhenFlagDisabled(): void
    {
        $this->assertFalse($this->service->isEnabled('permanent.disabled'));
    }

    public function testIsEnabledWithPercentageStrategy(): void
    {
        $this->service->addStrategy(new PercentageStrategy());

        $this->assertTrue($this->service->isEnabled('permanent.percentage', ['user_id' => '1']));
    }

    public function testNonExistentFlagReturnsFalse(): void
    {
        $this->assertFalse($this->service->isEnabled('non.existent'));
    }

    public function testNonExistentStrategyReturnsFalse(): void
    {
        $permanentFlags = [
            'test.flag' => ['enabled' => true, 'strategy' => 'non_existent_strategy'],
        ];

        $service = new PermanentFeatureFlagService($permanentFlags);

        $this->assertFalse($service->isEnabled('test.flag'));
    }

    public function testGetConfig(): void
    {
        $config = $this->service->getConfig('permanent.enabled');

        $this->assertIsArray($config);
        $this->assertTrue($config['enabled']);
        $this->assertEquals('simple', $config['strategy']);
    }

    public function testGetConfigNonExistent(): void
    {
        $config = $this->service->getConfig('non.existent');

        $this->assertNull($config);
    }

    public function testPaginate(): void
    {
        $result = $this->service->paginate(1, 50);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('flags', $result);
        $this->assertArrayHasKey('pagination', $result);
        $this->assertCount(3, $result['flags']);
        $this->assertArrayHasKey('permanent.enabled', $result['flags']);
        $this->assertArrayHasKey('permanent.disabled', $result['flags']);
        $this->assertArrayHasKey('permanent.percentage', $result['flags']);
        $this->assertEquals(3, $result['pagination']['total']);
        $this->assertEquals(1, $result['pagination']['page']);
        $this->assertEquals(1, $result['pagination']['pages']);
    }

    public function testExists(): void
    {
        $this->assertTrue($this->service->exists('permanent.enabled'));
        $this->assertFalse($this->service->exists('non.existent'));
    }
}
