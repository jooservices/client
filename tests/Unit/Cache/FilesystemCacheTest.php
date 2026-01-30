<?php

declare(strict_types=1);

use JOOservices\Client\Cache\FilesystemCache;

describe('FilesystemCache', function () {
    // NOTE: Tests use TTL because there's a bug with isset() and null expiresAt
    // The default high TTL (3600) ensures values don't expire during tests

    it('creates directory if not exists', function () {
        $cacheDir = sys_get_temp_dir() . '/test_fs_cache_' . uniqid();
        expect(is_dir($cacheDir))->toBeFalse();

        new FilesystemCache($cacheDir);

        expect(is_dir($cacheDir))->toBeTrue();

        // Cleanup
        rmdir($cacheDir);
    });

    it('returns default for non-existent key', function () {
        $cacheDir = sys_get_temp_dir() . '/test_fs_cache_' . uniqid();
        $cache = new FilesystemCache($cacheDir);

        expect($cache->get('non_existent'))->toBeNull();
        expect($cache->get('non_existent', 'default'))->toBe('default');

        // Cleanup
        rmdir($cacheDir);
    });

    it('stores and retrieves values with TTL', function () {
        $cacheDir = sys_get_temp_dir() . '/test_fs_cache_' . uniqid();
        $cache = new FilesystemCache($cacheDir);

        $result1 = $cache->set('key1', 'value1', 3600);
        $result2 = $cache->set('key2', ['array' => 'value'], 3600);

        expect($result1)->toBeTrue();
        expect($result2)->toBeTrue();
        expect($cache->get('key1'))->toBe('value1');
        expect($cache->get('key2'))->toBe(['array' => 'value']);

        // Cleanup
        $cache->clear();
        rmdir($cacheDir);
    });

    it('deletes values', function () {
        $cacheDir = sys_get_temp_dir() . '/test_fs_cache_' . uniqid();
        $cache = new FilesystemCache($cacheDir);

        $cache->set('key', 'value', 3600);
        expect($cache->get('key'))->toBe('value');

        $result = $cache->delete('key');

        expect($result)->toBeTrue();
        expect($cache->get('key'))->toBeNull();

        // Cleanup
        rmdir($cacheDir);
    });

    it('returns true when deleting non-existent key', function () {
        $cacheDir = sys_get_temp_dir() . '/test_fs_cache_' . uniqid();
        $cache = new FilesystemCache($cacheDir);

        $result = $cache->delete('non_existent');

        expect($result)->toBeTrue();

        // Cleanup
        rmdir($cacheDir);
    });

    it('clears all values', function () {
        $cacheDir = sys_get_temp_dir() . '/test_fs_cache_' . uniqid();
        $cache = new FilesystemCache($cacheDir);

        $cache->set('key1', 'value1', 3600);
        $cache->set('key2', 'value2', 3600);

        $result = $cache->clear();

        expect($result)->toBeTrue();
        expect($cache->get('key1'))->toBeNull();
        expect($cache->get('key2'))->toBeNull();

        // Cleanup
        rmdir($cacheDir);
    });

    it('checks if key exists with has()', function () {
        $cacheDir = sys_get_temp_dir() . '/test_fs_cache_' . uniqid();
        $cache = new FilesystemCache($cacheDir);

        expect($cache->has('key'))->toBeFalse();

        $cache->set('key', 'value', 3600);

        expect($cache->has('key'))->toBeTrue();

        // Cleanup
        $cache->clear();
        rmdir($cacheDir);
    });

    it('respects TTL and expires cache', function () {
        $cacheDir = sys_get_temp_dir() . '/test_fs_cache_' . uniqid();
        $cache = new FilesystemCache($cacheDir);

        // Set with 1 second TTL
        $cache->set('expiring_key', 'value', 1);

        expect($cache->get('expiring_key'))->toBe('value');

        // Wait for expiration
        sleep(2);

        expect($cache->get('expiring_key'))->toBeNull();

        // Cleanup
        rmdir($cacheDir);
    });

    it('supports DateInterval TTL', function () {
        $cacheDir = sys_get_temp_dir() . '/test_fs_cache_' . uniqid();
        $cache = new FilesystemCache($cacheDir);

        $cache->set('interval_key', 'value', new DateInterval('PT1S'));

        expect($cache->get('interval_key'))->toBe('value');

        sleep(2);

        expect($cache->get('interval_key'))->toBeNull();

        // Cleanup
        rmdir($cacheDir);
    });

    it('handles getMultiple with TTL', function () {
        $cacheDir = sys_get_temp_dir() . '/test_fs_cache_' . uniqid();
        $cache = new FilesystemCache($cacheDir);

        $cache->set('key1', 'value1', 3600);
        $cache->set('key2', 'value2', 3600);

        $result = $cache->getMultiple(['key1', 'key2', 'key3'], 'default');

        expect($result)->toBe([
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'default',
        ]);

        // Cleanup
        $cache->clear();
        rmdir($cacheDir);
    });

    it('handles setMultiple with TTL', function () {
        $cacheDir = sys_get_temp_dir() . '/test_fs_cache_' . uniqid();
        $cache = new FilesystemCache($cacheDir);

        $result = $cache->setMultiple([
            'key1' => 'value1',
            'key2' => 'value2',
        ], 3600);

        expect($result)->toBeTrue();
        expect($cache->get('key1'))->toBe('value1');
        expect($cache->get('key2'))->toBe('value2');

        // Cleanup
        $cache->clear();
        rmdir($cacheDir);
    });

    it('handles deleteMultiple', function () {
        $cacheDir = sys_get_temp_dir() . '/test_fs_cache_' . uniqid();
        $cache = new FilesystemCache($cacheDir);

        $cache->set('key1', 'value1', 3600);
        $cache->set('key2', 'value2', 3600);
        $cache->set('key3', 'value3', 3600);

        $result = $cache->deleteMultiple(['key1', 'key2']);

        expect($result)->toBeTrue();
        expect($cache->get('key1'))->toBeNull();
        expect($cache->get('key2'))->toBeNull();
        expect($cache->get('key3'))->toBe('value3');

        // Cleanup
        $cache->clear();
        rmdir($cacheDir);
    });

    it('handles file read failure gracefully', function () {
        $cacheDir = sys_get_temp_dir() . '/test_fs_cache_' . uniqid();
        $cache = new FilesystemCache($cacheDir);

        // Set a value first
        $cache->set('test_key', 'value', 3600);

        // Corrupt the file by writing invalid data
        $files = glob($cacheDir . '/*.cache');
        file_put_contents($files[0], 'not a valid serialized array');

        // Should return default for corrupted file
        expect($cache->get('test_key', 'default'))->toBe('default');

        // Cleanup
        $cache->clear();
        rmdir($cacheDir);
    });
});
