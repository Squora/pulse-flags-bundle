<?php

declare(strict_types=1);

namespace Pulse\FlagsBundle\Tests\Unit\Strategy;

use PHPUnit\Framework\TestCase;
use Pulse\FlagsBundle\Strategy\UserIdStrategy;

class UserIdStrategyTest extends TestCase
{
    private UserIdStrategy $strategy;

    protected function setUp(): void
    {
        $this->strategy = new UserIdStrategy();
    }

    public function testGetName(): void
    {
        $this->assertEquals('user_id', $this->strategy->getName());
    }

    public function testWhitelistAllows(): void
    {
        $config = ['whitelist' => ['1', '2', '3']];

        $this->assertTrue($this->strategy->isEnabled($config, ['user_id' => '1']));
        $this->assertTrue($this->strategy->isEnabled($config, ['user_id' => '2']));
        $this->assertTrue($this->strategy->isEnabled($config, ['user_id' => '3']));
    }

    public function testWhitelistDenies(): void
    {
        $config = ['whitelist' => ['1', '2', '3']];

        $this->assertFalse($this->strategy->isEnabled($config, ['user_id' => '4']));
        $this->assertFalse($this->strategy->isEnabled($config, ['user_id' => '999']));
    }

    public function testBlacklistDenies(): void
    {
        $config = ['blacklist' => ['99', '100']];

        $this->assertFalse($this->strategy->isEnabled($config, ['user_id' => '99']));
        $this->assertFalse($this->strategy->isEnabled($config, ['user_id' => '100']));
    }

    public function testBlacklistAllows(): void
    {
        $config = ['blacklist' => ['99', '100']];

        $this->assertTrue($this->strategy->isEnabled($config, ['user_id' => '1']));
        $this->assertTrue($this->strategy->isEnabled($config, ['user_id' => '50']));
    }

    public function testWhitelistTakesPrecedenceOverBlacklist(): void
    {
        $config = [
            'whitelist' => ['1', '2'],
            'blacklist' => ['2', '3'],
        ];

        // User 2 is in both - whitelist wins
        $this->assertTrue($this->strategy->isEnabled($config, ['user_id' => '2']));

        // User 3 only in blacklist
        $this->assertFalse($this->strategy->isEnabled($config, ['user_id' => '3']));

        // User 1 only in whitelist
        $this->assertTrue($this->strategy->isEnabled($config, ['user_id' => '1']));

        // User 4 in neither - allowed when no whitelist only
        $this->assertFalse($this->strategy->isEnabled($config, ['user_id' => '4']));
    }

    public function testNoWhitelistNoBlacklistAllowsAll(): void
    {
        $config = [];

        $this->assertTrue($this->strategy->isEnabled($config, ['user_id' => '1']));
        $this->assertTrue($this->strategy->isEnabled($config, ['user_id' => '999']));
    }

    public function testNoUserIdInContextReturnsFalse(): void
    {
        $config = ['whitelist' => ['1', '2']];

        $this->assertFalse($this->strategy->isEnabled($config, []));
    }

    public function testStringUserIds(): void
    {
        $config = ['whitelist' => ['user_abc', 'user_xyz']];

        $this->assertTrue($this->strategy->isEnabled($config, ['user_id' => 'user_abc']));
        $this->assertFalse($this->strategy->isEnabled($config, ['user_id' => 'user_def']));
    }
}
