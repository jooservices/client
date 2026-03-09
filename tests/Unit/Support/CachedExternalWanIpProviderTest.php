<?php

declare(strict_types=1);

use JOOservices\Client\Support\CachedExternalWanIpProvider;

test('it caches wan ip within ttl window', function () {
    $calls = 0;

    $provider = new CachedExternalWanIpProvider(
        cacheTtlSeconds: 60,
        resolver: function () use (&$calls): ?string {
            $calls++;

            return '198.51.100.10';
        }
    );

    expect($provider->getPublicIp())->toBe('198.51.100.10');
    expect($provider->getPublicIp())->toBe('198.51.100.10');
    expect($calls)->toBe(1);
});

test('it keeps last cached wan ip when resolver fails', function () {
    $calls = 0;

    $provider = new CachedExternalWanIpProvider(
        cacheTtlSeconds: 0,
        resolver: function () use (&$calls): ?string {
            $calls++;

            if ($calls === 1) {
                return '198.51.100.11';
            }

            throw new RuntimeException('resolver unavailable');
        }
    );

    expect($provider->getPublicIp())->toBe('198.51.100.11');
    expect($provider->getPublicIp())->toBe('198.51.100.11');
    expect($calls)->toBe(2);
});

test('it returns null when no cached value exists and resolver fails', function () {
    $provider = new CachedExternalWanIpProvider(
        cacheTtlSeconds: 0,
        resolver: function (): ?string {
            throw new RuntimeException('network down');
        }
    );

    expect($provider->getPublicIp())->toBeNull();
});
