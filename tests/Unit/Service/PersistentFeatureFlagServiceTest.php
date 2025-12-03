<?php

declare(strict_types=1);

namespace Pulse\FlagsBundle\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Pulse\FlagsBundle\Service\PersistentFeatureFlagService;
use Pulse\FlagsBundle\Storage\PhpStorage;
use Pulse\FlagsBundle\Strategy\PercentageStrategy;
use Pulse\FlagsBundle\Strategy\UserIdStrategy;

class PersistentFeatureFlagServiceTest extends TestCase
{
    private PersistentFeatureFlagService $service;
    private PhpStorage $storage;

    protected function setUp(): void
    {
        $this->storage = new PhpStorage(); // In-memory mode
        $this->service = new PersistentFeatureFlagService(
            $this->storage,
            new NullLogger()
        );
    }

    public function testIsEnabledWithSimpleStrategy(): void
    {
        $this->storage->set('test.flag', ['enabled' => true, 'strategy' => 'simple']);

        $this->assertTrue($this->service->isEnabled('test.flag'));
    }

    public function testIsDisabledWhenFlagDisabled(): void
    {
        $this->storage->set('test.flag', ['enabled' => false, 'strategy' => 'simple']);

        $this->assertFalse($this->service->isEnabled('test.flag'));
    }

    public function testIsEnabledWithPercentageStrategy(): void
    {
        $this->service->addStrategy(new PercentageStrategy());
        $this->storage->set('test.flag', [
            'enabled' => true,
            'strategy' => 'percentage',
            'percentage' => 100,
        ]);

        $this->assertTrue($this->service->isEnabled('test.flag', ['user_id' => '1']));
    }

    public function testIsDisabledWithPercentageStrategyZero(): void
    {
        $this->service->addStrategy(new PercentageStrategy());
        $this->storage->set('test.flag', [
            'enabled' => true,
            'strategy' => 'percentage',
            'percentage' => 0,
        ]);

        $this->assertFalse($this->service->isEnabled('test.flag', ['user_id' => '1']));
    }

    public function testIsEnabledWithUserIdStrategy(): void
    {
        $this->service->addStrategy(new UserIdStrategy());
        $this->storage->set('test.flag', [
            'enabled' => true,
            'strategy' => 'user_id',
            'whitelist' => ['1', '2', '3'],
        ]);

        $this->assertTrue($this->service->isEnabled('test.flag', ['user_id' => '1']));
        $this->assertFalse($this->service->isEnabled('test.flag', ['user_id' => '99']));
    }

    public function testNonExistentFlagReturnsFalse(): void
    {
        $this->assertFalse($this->service->isEnabled('non.existent'));
    }

    public function testNonExistentStrategyReturnsFalse(): void
    {
        $this->storage->set('test.flag', [
            'enabled' => true,
            'strategy' => 'non_existent_strategy',
        ]);

        $this->assertFalse($this->service->isEnabled('test.flag'));
    }

    public function testConfigure(): void
    {
        $this->service->configure('new.flag', [
            'enabled' => true,
            'strategy' => 'simple',
        ]);

        $this->assertTrue($this->service->isEnabled('new.flag'));
    }

    public function testEnable(): void
    {
        $this->storage->set('test.flag', ['enabled' => false]);

        $this->service->enable('test.flag');

        $this->assertTrue($this->service->isEnabled('test.flag'));
    }

    public function testEnableWithOptions(): void
    {
        $this->service->addStrategy(new PercentageStrategy());

        $this->service->enable('test.flag', [
            'strategy' => 'percentage',
            'percentage' => 50,
        ]);

        $config = $this->service->getConfig('test.flag');
        $this->assertTrue($config['enabled']);
        $this->assertEquals('percentage', $config['strategy']);
        $this->assertEquals(50, $config['percentage']);
    }

    public function testDisable(): void
    {
        $this->storage->set('test.flag', ['enabled' => true]);

        $this->service->disable('test.flag');

        $this->assertFalse($this->service->isEnabled('test.flag'));
    }

    public function testRemove(): void
    {
        $this->storage->set('test.flag', ['enabled' => true]);
        $this->assertTrue($this->service->exists('test.flag'));

        $this->service->remove('test.flag');

        $this->assertFalse($this->service->exists('test.flag'));
    }

    public function testGetConfig(): void
    {
        $this->storage->set('test.flag', [
            'enabled' => true,
            'strategy' => 'simple',
            'description' => 'Test flag',
        ]);

        $config = $this->service->getConfig('test.flag');

        $this->assertIsArray($config);
        $this->assertTrue($config['enabled']);
        $this->assertEquals('simple', $config['strategy']);
        $this->assertEquals('Test flag', $config['description']);
    }

    public function testGetConfigNonExistent(): void
    {
        $config = $this->service->getConfig('non.existent');

        $this->assertNull($config);
    }

    public function testPaginate(): void
    {
        $this->storage->set('test.flag1', ['enabled' => true]);
        $this->storage->set('test.flag2', ['enabled' => false]);

        $result = $this->service->paginate(1, 50);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('flags', $result);
        $this->assertArrayHasKey('pagination', $result);
        $this->assertArrayHasKey('test.flag1', $result['flags']);
        $this->assertArrayHasKey('test.flag2', $result['flags']);
    }

    public function testExists(): void
    {
        $this->storage->set('test.flag', ['enabled' => true]);

        $this->assertTrue($this->service->exists('test.flag'));
        $this->assertFalse($this->service->exists('non.existent'));
    }
}
