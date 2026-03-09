<?php

declare(strict_types=1);

namespace Tests\Unit\Cache;

use JOOservices\Client\Cache\FilesystemCache;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('unit')]
class FilesystemCacheTest extends TestCase
{
    public function test_creates_directory_if_not_exists(): void
    {
        $cacheDir = sys_get_temp_dir() . '/test_fs_cache_' . uniqid();
        $this->assertFalse(is_dir($cacheDir));

        new FilesystemCache($cacheDir);

        $this->assertTrue(is_dir($cacheDir));

        rmdir($cacheDir);
    }

    public function test_returns_default_for_non_existent_key(): void
    {
        $cacheDir = sys_get_temp_dir() . '/test_fs_cache_' . uniqid();
        $cache = new FilesystemCache($cacheDir);

        $this->assertNull($cache->get('non_existent'));
        $this->assertSame('default', $cache->get('non_existent', 'default'));

        rmdir($cacheDir);
    }

    public function test_stores_and_retrieves_values_with_TTL(): void
    {
        $cacheDir = sys_get_temp_dir() . '/test_fs_cache_' . uniqid();
        $cache = new FilesystemCache($cacheDir);

        $result1 = $cache->set('key1', 'value1', 3600);
        $result2 = $cache->set('key2', ['array' => 'value'], 3600);

        $this->assertTrue($result1);
        $this->assertTrue($result2);
        $this->assertSame('value1', $cache->get('key1'));
        $this->assertSame(['array' => 'value'], $cache->get('key2'));

        $cache->clear();
        rmdir($cacheDir);
    }

    public function test_deletes_values(): void
    {
        $cacheDir = sys_get_temp_dir() . '/test_fs_cache_' . uniqid();
        $cache = new FilesystemCache($cacheDir);

        $cache->set('key', 'value', 3600);
        $this->assertSame('value', $cache->get('key'));

        $result = $cache->delete('key');

        $this->assertTrue($result);
        $this->assertNull($cache->get('key'));

        rmdir($cacheDir);
    }

    public function test_returns_true_when_deleting_non_existent_key(): void
    {
        $cacheDir = sys_get_temp_dir() . '/test_fs_cache_' . uniqid();
        $cache = new FilesystemCache($cacheDir);

        $result = $cache->delete('non_existent');

        $this->assertTrue($result);

        rmdir($cacheDir);
    }

    public function test_clears_all_values(): void
    {
        $cacheDir = sys_get_temp_dir() . '/test_fs_cache_' . uniqid();
        $cache = new FilesystemCache($cacheDir);

        $cache->set('key1', 'value1', 3600);
        $cache->set('key2', 'value2', 3600);

        $result = $cache->clear();

        $this->assertTrue($result);
        $this->assertNull($cache->get('key1'));
        $this->assertNull($cache->get('key2'));

        rmdir($cacheDir);
    }

    public function test_checks_if_key_exists_with_has(): void
    {
        $cacheDir = sys_get_temp_dir() . '/test_fs_cache_' . uniqid();
        $cache = new FilesystemCache($cacheDir);

        $this->assertFalse($cache->has('key'));

        $cache->set('key', 'value', 3600);

        $this->assertTrue($cache->has('key'));

        $cache->clear();
        rmdir($cacheDir);
    }

    public function test_respects_TTL_and_expires_cache(): void
    {
        $cacheDir = sys_get_temp_dir() . '/test_fs_cache_' . uniqid();
        $cache = new FilesystemCache($cacheDir);

        $cache->set('expiring_key', 'value', 1);

        $this->assertSame('value', $cache->get('expiring_key'));

        sleep(2);

        $this->assertNull($cache->get('expiring_key'));

        rmdir($cacheDir);
    }

    public function test_supports_DateInterval_TTL(): void
    {
        $cacheDir = sys_get_temp_dir() . '/test_fs_cache_' . uniqid();
        $cache = new FilesystemCache($cacheDir);

        $cache->set('interval_key', 'value', new \DateInterval('PT1S'));

        $this->assertSame('value', $cache->get('interval_key'));

        sleep(2);

        $this->assertNull($cache->get('interval_key'));

        rmdir($cacheDir);
    }

    public function test_handles_getMultiple_with_TTL(): void
    {
        $cacheDir = sys_get_temp_dir() . '/test_fs_cache_' . uniqid();
        $cache = new FilesystemCache($cacheDir);

        $cache->set('key1', 'value1', 3600);
        $cache->set('key2', 'value2', 3600);

        $result = $cache->getMultiple(['key1', 'key2', 'key3'], 'default');

        $this->assertSame([
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'default',
        ], $result);

        $cache->clear();
        rmdir($cacheDir);
    }

    public function test_handles_setMultiple_with_TTL(): void
    {
        $cacheDir = sys_get_temp_dir() . '/test_fs_cache_' . uniqid();
        $cache = new FilesystemCache($cacheDir);

        $result = $cache->setMultiple([
            'key1' => 'value1',
            'key2' => 'value2',
        ], 3600);

        $this->assertTrue($result);
        $this->assertSame('value1', $cache->get('key1'));
        $this->assertSame('value2', $cache->get('key2'));

        $cache->clear();
        rmdir($cacheDir);
    }

    public function test_handles_deleteMultiple(): void
    {
        $cacheDir = sys_get_temp_dir() . '/test_fs_cache_' . uniqid();
        $cache = new FilesystemCache($cacheDir);

        $cache->set('key1', 'value1', 3600);
        $cache->set('key2', 'value2', 3600);
        $cache->set('key3', 'value3', 3600);

        $result = $cache->deleteMultiple(['key1', 'key2']);

        $this->assertTrue($result);
        $this->assertNull($cache->get('key1'));
        $this->assertNull($cache->get('key2'));
        $this->assertSame('value3', $cache->get('key3'));

        $cache->clear();
        rmdir($cacheDir);
    }

    public function test_handles_file_read_failure_gracefully(): void
    {
        $cacheDir = sys_get_temp_dir() . '/test_fs_cache_' . uniqid();
        $cache = new FilesystemCache($cacheDir);

        $cache->set('test_key', 'value', 3600);

        $files = glob($cacheDir . '/*.cache');
        file_put_contents($files[0], 'not a valid serialized array');

        $this->assertSame('default', $cache->get('test_key', 'default'));

        $cache->clear();
        rmdir($cacheDir);
    }
}
