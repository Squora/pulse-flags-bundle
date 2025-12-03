<?php

declare(strict_types=1);

namespace Pulse\FlagsBundle\Tests\Unit\Strategy;

use PHPUnit\Framework\TestCase;
use Pulse\FlagsBundle\Strategy\PercentageStrategy;

class PercentageStrategyTest extends TestCase
{
    private PercentageStrategy $strategy;

    protected function setUp(): void
    {
        $this->strategy = new PercentageStrategy();
    }

    public function testGetName(): void
    {
        $this->assertEquals('percentage', $this->strategy->getName());
    }

    public function testPercentage100AlwaysEnabled(): void
    {
        $config = ['percentage' => 100];

        $this->assertTrue($this->strategy->isEnabled($config, ['user_id' => '1']));
        $this->assertTrue($this->strategy->isEnabled($config, ['user_id' => '999']));
        $this->assertTrue($this->strategy->isEnabled($config, ['user_id' => 'abc']));
    }

    public function testPercentage0AlwaysDisabled(): void
    {
        $config = ['percentage' => 0];

        $this->assertFalse($this->strategy->isEnabled($config, ['user_id' => '1']));
        $this->assertFalse($this->strategy->isEnabled($config, ['user_id' => '999']));
        $this->assertFalse($this->strategy->isEnabled($config, ['user_id' => 'abc']));
    }

    public function testConsistentBucketing(): void
    {
        $config = ['percentage' => 50];
        $userId = '12345';

        // Same user should always get same result
        $result1 = $this->strategy->isEnabled($config, ['user_id' => $userId]);
        $result2 = $this->strategy->isEnabled($config, ['user_id' => $userId]);
        $result3 = $this->strategy->isEnabled($config, ['user_id' => $userId]);

        $this->assertEquals($result1, $result2);
        $this->assertEquals($result2, $result3);
    }

    public function testFallbackToSessionId(): void
    {
        $config = ['percentage' => 50];

        $result = $this->strategy->isEnabled($config, ['session_id' => 'session123']);

        $this->assertIsBool($result);
    }

    public function testFallbackToRandomWithoutIdentifier(): void
    {
        $config = ['percentage' => 50];

        // Without user_id or session_id, uses uniqid (random)
        $result = $this->strategy->isEnabled($config, []);

        $this->assertIsBool($result);
    }

    public function testDistribution(): void
    {
        $config = ['percentage' => 30];
        $enabledCount = 0;
        $total = 1000;

        // Test with many different user IDs
        for ($i = 0; $i < $total; $i++) {
            if ($this->strategy->isEnabled($config, ['user_id' => (string)$i])) {
                $enabledCount++;
            }
        }

        // Should be roughly 30% (allow 10% margin of error)
        $actualPercentage = ($enabledCount / $total) * 100;
        $this->assertGreaterThan(20, $actualPercentage);
        $this->assertLessThan(40, $actualPercentage);
    }
}
