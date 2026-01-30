<?php

declare(strict_types=1);

use JOOservices\Client\Cache\MemoryCache;

describe('MemoryCache', function () {
    it('returns default for non-existent key', function () {
        $cache = new MemoryCache();

        expect($cache->get('non_existent'))->toBeNull();
        expect($cache->get('non_existent', 'default'))->toBe('default');
    });

    it('stores and retrieves values', function () {
        $cache = new MemoryCache();

        $cache->set('key1', 'value1');
        $cache->set('key2', ['array' => 'value']);

        expect($cache->get('key1'))->toBe('value1');
        expect($cache->get('key2'))->toBe(['array' => 'value']);
    });

    it('deletes values', function () {
        $cache = new MemoryCache();

        $cache->set('key', 'value');
        expect($cache->get('key'))->toBe('value');

        $result = $cache->delete('key');

        expect($result)->toBeTrue();
        expect($cache->get('key'))->toBeNull();
    });

    it('clears all values', function () {
        $cache = new MemoryCache();

        $cache->set('key1', 'value1');
        $cache->set('key2', 'value2');

        $result = $cache->clear();

        expect($result)->toBeTrue();
        expect($cache->get('key1'))->toBeNull();
        expect($cache->get('key2'))->toBeNull();
    });

    it('checks if key exists with has()', function () {
        $cache = new MemoryCache();

        expect($cache->has('key'))->toBeFalse();

        $cache->set('key', 'value');

        expect($cache->has('key'))->toBeTrue();
    });

    it('handles null values correctly with has()', function () {
        $cache = new MemoryCache();

        $cache->set('null_key', null);

        // has() should return true even for null values
        expect($cache->has('null_key'))->toBeTrue();
    });

    it('respects TTL and expires cache', function () {
        $cache = new MemoryCache();

        // Set with 1 second TTL
        $cache->set('expiring_key', 'value', 1);

        expect($cache->get('expiring_key'))->toBe('value');

        // Wait for expiration
        sleep(2);

        expect($cache->get('expiring_key'))->toBeNull();
    });

    it('supports DateInterval TTL', function () {
        $cache = new MemoryCache();

        $cache->set('interval_key', 'value', new DateInterval('PT1S'));

        expect($cache->get('interval_key'))->toBe('value');

        sleep(2);

        expect($cache->get('interval_key'))->toBeNull();
    });

    it('handles getMultiple', function () {
        $cache = new MemoryCache();

        $cache->set('key1', 'value1');
        $cache->set('key2', 'value2');

        $result = $cache->getMultiple(['key1', 'key2', 'key3'], 'default');

        expect($result)->toBe([
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'default',
        ]);
    });

    it('handles setMultiple', function () {
        $cache = new MemoryCache();

        $result = $cache->setMultiple([
            'key1' => 'value1',
            'key2' => 'value2',
        ]);

        expect($result)->toBeTrue();
        expect($cache->get('key1'))->toBe('value1');
        expect($cache->get('key2'))->toBe('value2');
    });

    it('handles deleteMultiple', function () {
        $cache = new MemoryCache();

        $cache->set('key1', 'value1');
        $cache->set('key2', 'value2');
        $cache->set('key3', 'value3');

        $result = $cache->deleteMultiple(['key1', 'key2']);

        expect($result)->toBeTrue();
        expect($cache->get('key1'))->toBeNull();
        expect($cache->get('key2'))->toBeNull();
        expect($cache->get('key3'))->toBe('value3');
    });
});
