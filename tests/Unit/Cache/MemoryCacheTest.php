<?php

declare(strict_types=1);

namespace Tests\Unit\Cache;

use JOOservices\Client\Cache\MemoryCache;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('unit')]
class MemoryCacheTest extends TestCase
{
    public function test_returns_default_for_non_existent_key(): void
    {
        $cache = new MemoryCache();

        $this->assertNull($cache->get('non_existent'));
        $this->assertSame('default', $cache->get('non_existent', 'default'));
    }

    public function test_stores_and_retrieves_values(): void
    {
        $cache = new MemoryCache();

        $cache->set('key1', 'value1');
        $cache->set('key2', ['array' => 'value']);

        $this->assertSame('value1', $cache->get('key1'));
        $this->assertSame(['array' => 'value'], $cache->get('key2'));
    }

    public function test_deletes_values(): void
    {
        $cache = new MemoryCache();

        $cache->set('key', 'value');
        $this->assertSame('value', $cache->get('key'));

        $result = $cache->delete('key');

        $this->assertTrue($result);
        $this->assertNull($cache->get('key'));
    }

    public function test_clears_all_values(): void
    {
        $cache = new MemoryCache();

        $cache->set('key1', 'value1');
        $cache->set('key2', 'value2');

        $result = $cache->clear();

        $this->assertTrue($result);
        $this->assertNull($cache->get('key1'));
        $this->assertNull($cache->get('key2'));
    }

    public function test_checks_if_key_exists_with_has(): void
    {
        $cache = new MemoryCache();

        $this->assertFalse($cache->has('key'));

        $cache->set('key', 'value');

        $this->assertTrue($cache->has('key'));
    }

    public function test_handles_null_values_correctly_with_has(): void
    {
        $cache = new MemoryCache();

        $cache->set('null_key', null);

        $this->assertTrue($cache->has('null_key'));
    }

    public function test_respects_TTL_and_expires_cache(): void
    {
        $cache = new MemoryCache();

        $cache->set('expiring_key', 'value', 1);

        $this->assertSame('value', $cache->get('expiring_key'));

        sleep(2);

        $this->assertNull($cache->get('expiring_key'));
    }

    public function test_supports_DateInterval_TTL(): void
    {
        $cache = new MemoryCache();

        $cache->set('interval_key', 'value', new \DateInterval('PT1S'));

        $this->assertSame('value', $cache->get('interval_key'));

        sleep(2);

        $this->assertNull($cache->get('interval_key'));
    }

    public function test_handles_getMultiple(): void
    {
        $cache = new MemoryCache();

        $cache->set('key1', 'value1');
        $cache->set('key2', 'value2');

        $result = $cache->getMultiple(['key1', 'key2', 'key3'], 'default');

        $this->assertSame([
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'default',
        ], $result);
    }

    public function test_handles_setMultiple(): void
    {
        $cache = new MemoryCache();

        $result = $cache->setMultiple([
            'key1' => 'value1',
            'key2' => 'value2',
        ]);

        $this->assertTrue($result);
        $this->assertSame('value1', $cache->get('key1'));
        $this->assertSame('value2', $cache->get('key2'));
    }

    public function test_handles_deleteMultiple(): void
    {
        $cache = new MemoryCache();

        $cache->set('key1', 'value1');
        $cache->set('key2', 'value2');
        $cache->set('key3', 'value3');

        $result = $cache->deleteMultiple(['key1', 'key2']);

        $this->assertTrue($result);
        $this->assertNull($cache->get('key1'));
        $this->assertNull($cache->get('key2'));
        $this->assertSame('value3', $cache->get('key3'));
    }
}
