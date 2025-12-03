<?php

declare(strict_types=1);

namespace Pulse\FlagsBundle\Tests\Unit\Storage;

use PHPUnit\Framework\TestCase;
use Pulse\FlagsBundle\Storage\PhpStorage;

class PhpStorageTest extends TestCase
{
    private PhpStorage $storage;

    protected function setUp(): void
    {
        // Use in-memory mode (no file path)
        $this->storage = new PhpStorage();
        $this->storage->set('test_flag', ['enabled' => true, 'strategy' => 'simple']);
    }

    public function testGet(): void
    {
        $config = $this->storage->get('test_flag');

        $this->assertIsArray($config);
        $this->assertTrue($config['enabled']);
        $this->assertEquals('simple', $config['strategy']);
    }

    public function testGetNonExistent(): void
    {
        $config = $this->storage->get('non_existent');

        $this->assertNull($config);
    }

    public function testSet(): void
    {
        $this->storage->set('new_flag', ['enabled' => false, 'strategy' => 'percentage']);

        $config = $this->storage->get('new_flag');
        $this->assertIsArray($config);
        $this->assertFalse($config['enabled']);
        $this->assertEquals('percentage', $config['strategy']);
    }

    public function testHas(): void
    {
        $this->assertTrue($this->storage->has('test_flag'));
        $this->assertFalse($this->storage->has('non_existent'));
    }

    public function testRemove(): void
    {
        $this->assertTrue($this->storage->has('test_flag'));

        $this->storage->remove('test_flag');

        $this->assertFalse($this->storage->has('test_flag'));
    }

    public function testAll(): void
    {
        $this->storage->set('flag1', ['enabled' => true]);
        $this->storage->set('flag2', ['enabled' => false]);

        $all = $this->storage->all();

        $this->assertIsArray($all);
        $this->assertCount(3, $all); // test_flag + flag1 + flag2
        $this->assertArrayHasKey('test_flag', $all);
        $this->assertArrayHasKey('flag1', $all);
        $this->assertArrayHasKey('flag2', $all);
    }

    public function testClear(): void
    {
        $this->storage->set('flag1', ['enabled' => true]);
        $this->storage->set('flag2', ['enabled' => false]);

        $this->assertCount(3, $this->storage->all());

        $this->storage->clear();

        $this->assertCount(0, $this->storage->all());
    }

    public function testUpdate(): void
    {
        $this->storage->set('test_flag', ['enabled' => false, 'strategy' => 'percentage', 'percentage' => 50]);

        $config = $this->storage->get('test_flag');
        $this->assertFalse($config['enabled']);
        $this->assertEquals('percentage', $config['strategy']);
        $this->assertEquals(50, $config['percentage']);
    }
}
