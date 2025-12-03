<?php

declare(strict_types=1);

namespace Pulse\FlagsBundle\Tests\Unit\Strategy;

use PHPUnit\Framework\TestCase;
use Pulse\FlagsBundle\Strategy\DateRangeStrategy;

class DateRangeStrategyTest extends TestCase
{
    private DateRangeStrategy $strategy;

    protected function setUp(): void
    {
        $this->strategy = new DateRangeStrategy();
    }

    public function testGetName(): void
    {
        $this->assertEquals('date_range', $this->strategy->getName());
    }

    public function testDateWithinRange(): void
    {
        $config = [
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
        ];

        $context = ['current_date' => new \DateTime('2025-06-15')];

        $this->assertTrue($this->strategy->isEnabled($config, $context));
    }

    public function testDateBeforeRange(): void
    {
        $config = [
            'start_date' => '2025-06-01',
            'end_date' => '2025-12-31',
        ];

        $context = ['current_date' => new \DateTime('2025-05-31')];

        $this->assertFalse($this->strategy->isEnabled($config, $context));
    }

    public function testDateAfterRange(): void
    {
        $config = [
            'start_date' => '2025-01-01',
            'end_date' => '2025-06-30',
        ];

        $context = ['current_date' => new \DateTime('2025-07-01')];

        $this->assertFalse($this->strategy->isEnabled($config, $context));
    }

    public function testOnlyStartDate(): void
    {
        $config = ['start_date' => '2025-06-01'];

        $beforeContext = ['current_date' => new \DateTime('2025-05-31')];
        $afterContext = ['current_date' => new \DateTime('2025-06-15')];

        $this->assertFalse($this->strategy->isEnabled($config, $beforeContext));
        $this->assertTrue($this->strategy->isEnabled($config, $afterContext));
    }

    public function testOnlyEndDate(): void
    {
        $config = ['end_date' => '2025-06-30'];

        $beforeContext = ['current_date' => new \DateTime('2025-06-15')];
        $afterContext = ['current_date' => new \DateTime('2025-07-01')];

        $this->assertTrue($this->strategy->isEnabled($config, $beforeContext));
        $this->assertFalse($this->strategy->isEnabled($config, $afterContext));
    }

    public function testNoDateConfigAlwaysEnabled(): void
    {
        $config = [];
        $context = ['current_date' => new \DateTime('2025-01-01')];

        $this->assertTrue($this->strategy->isEnabled($config, $context));
    }

    public function testUsesCurrentDateByDefault(): void
    {
        $config = [
            'start_date' => '2020-01-01',
            'end_date' => '2030-12-31',
        ];

        // No current_date in context - should use current time
        $this->assertTrue($this->strategy->isEnabled($config, []));
    }

    public function testDateOnBoundary(): void
    {
        $config = [
            'start_date' => '2025-06-01',
            'end_date' => '2025-06-30',
        ];

        $startContext = ['current_date' => new \DateTime('2025-06-01 00:00:00')];
        $endContext = ['current_date' => new \DateTime('2025-06-30 23:59:59')];

        $this->assertTrue($this->strategy->isEnabled($config, $startContext));
        $this->assertTrue($this->strategy->isEnabled($config, $endContext));
    }

    public function testInvalidDateFormat(): void
    {
        $config = ['start_date' => 'invalid-date'];
        $context = ['current_date' => new \DateTime()];

        // Should handle gracefully and return false or throw
        $result = $this->strategy->isEnabled($config, $context);
        $this->assertIsBool($result);
    }
}
