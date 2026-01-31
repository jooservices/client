<?php

declare(strict_types=1);

use JOOservices\Client\Resilience\Storage\InMemoryStateStore;

describe('InMemoryStateStore', function () {
    it('starts with circuit closed', function () {
        $store = new InMemoryStateStore();

        expect($store->isCircuitOpen(3, 5000))->toBeFalse();
        expect($store->isHalfOpen(5000))->toBeFalse();
    });

    it('records failures and opens circuit at threshold', function () {
        $store = new InMemoryStateStore();
        $threshold = 2;

        $store->recordFailure();
        expect($store->isCircuitOpen($threshold, 5000))->toBeFalse();

        $store->recordFailure();
        expect($store->isCircuitOpen($threshold, 5000))->toBeTrue();
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

        // Open the circuit
        $store->recordFailure();
        $store->recordFailure();
        $store->recordFailure();
        expect($store->isCircuitOpen(3, 5000))->toBeTrue();

        $store->reset();

        // Circuit should be closed after reset
        expect($store->isCircuitOpen(3, 5000))->toBeFalse();
        expect($store->isHalfOpen(5000))->toBeFalse();
    });

    it('records success and resets failures when closed', function () {
        $store = new InMemoryStateStore();

        $store->recordFailure();
        $store->recordFailure();
        expect($store->isCircuitOpen(3, 5000))->toBeFalse();

        $store->recordSuccess();

        // After success, should still be closed and not open even with more failures
        $store->recordFailure();
        expect($store->isCircuitOpen(3, 5000))->toBeFalse();
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
