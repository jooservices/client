<?php

declare(strict_types=1);

use JOOservices\Client\Resilience\Storage\InMemoryStateStore;

describe('InMemoryStateStore', function () {
    it('starts with zero failure count', function () {
        $store = new InMemoryStateStore();

        expect($store->getFailureCount())->toBe(0);
        expect($store->getLastFailureTime())->toBeNull();
    });

    it('records failures and increments count', function () {
        $store = new InMemoryStateStore();

        $store->recordFailure();
        expect($store->getFailureCount())->toBe(1);
        expect($store->getLastFailureTime())->not->toBeNull();

        $store->recordFailure();
        expect($store->getFailureCount())->toBe(2);
    });

    it('tracks last failure time', function () {
        $store = new InMemoryStateStore();

        $before = microtime(true);
        $store->recordFailure();
        $after = microtime(true);

        $lastFailure = $store->getLastFailureTime();

        expect($lastFailure)->toBeGreaterThanOrEqual($before);
        expect($lastFailure)->toBeLessThanOrEqual($after);
    });

    it('opens circuit after threshold reached', function () {
        $store = new InMemoryStateStore();
        $threshold = 3;
        $recoveryTimeoutMs = 5000;

        // Before threshold
        $store->recordFailure();
        $store->recordFailure();
        expect($store->isCircuitOpen($threshold, $recoveryTimeoutMs))->toBeFalse();

        // At threshold
        $store->recordFailure();
        expect($store->isCircuitOpen($threshold, $recoveryTimeoutMs))->toBeTrue();
    });

    it('resets state completely', function () {
        $store = new InMemoryStateStore();

        $store->recordFailure();
        $store->recordFailure();
        $store->recordFailure();

        $store->reset();

        expect($store->getFailureCount())->toBe(0);
        expect($store->getLastFailureTime())->toBeNull();
        expect($store->isCircuitOpen(3, 5000))->toBeFalse();
    });

    it('records success and resets count when closed', function () {
        $store = new InMemoryStateStore();

        $store->recordFailure();
        $store->recordFailure();
        expect($store->getFailureCount())->toBe(2);

        $store->recordSuccess();

        expect($store->getFailureCount())->toBe(0);
    });

    it('detects half-open state after recovery timeout', function () {
        $store = new InMemoryStateStore();
        $threshold = 1;
        $recoveryTimeoutMs = 100; // 100ms for testing

        // Open the circuit
        $store->recordFailure();
        expect($store->isCircuitOpen($threshold, $recoveryTimeoutMs))->toBeTrue();
        expect($store->isHalfOpen($recoveryTimeoutMs))->toBeFalse();

        // Wait for recovery timeout
        usleep(150 * 1000); // 150ms

        expect($store->isHalfOpen($recoveryTimeoutMs))->toBeTrue();
    });

    it('tracks half-open successes', function () {
        $store = new InMemoryStateStore();

        // Open circuit first
        $store->recordFailure();
        $store->isCircuitOpen(1, 100);

        // Wait for half-open
        usleep(150 * 1000);

        $store->reportSuccessInHalfOpen();
        $store->reportSuccessInHalfOpen();

        expect($store->checkHalfOpenRecovery(2))->toBeTrue();
        expect($store->checkHalfOpenRecovery(3))->toBeFalse();
    });

    it('resets half-open successes on failure', function () {
        $store = new InMemoryStateStore();

        // Open circuit
        $store->recordFailure();
        $store->isCircuitOpen(1, 100);
        usleep(150 * 1000);

        // Some successes in half-open
        $store->reportSuccessInHalfOpen();
        expect($store->checkHalfOpenRecovery(1))->toBeTrue();

        // Failure resets
        $store->recordFailure();
        expect($store->checkHalfOpenRecovery(1))->toBeFalse();
    });
});
