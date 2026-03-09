<?php

declare(strict_types=1);

namespace Tests\Unit\Resilience;

use JOOservices\Client\Resilience\Storage\InMemoryStateStore;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('unit')]
class InMemoryStateStoreTest extends TestCase
{
    public function test_starts_with_circuit_closed(): void
    {
        $store = new InMemoryStateStore();

        $this->assertFalse($store->isCircuitOpen(3, 5000));
        $this->assertFalse($store->isHalfOpen(5000));
    }

    public function test_records_failures_and_opens_circuit_at_threshold(): void
    {
        $store = new InMemoryStateStore();
        $threshold = 2;

        $store->recordFailure();
        $this->assertFalse($store->isCircuitOpen($threshold, 5000));

        $store->recordFailure();
        $this->assertTrue($store->isCircuitOpen($threshold, 5000));
    }

    public function test_opens_circuit_after_threshold_reached(): void
    {
        $store = new InMemoryStateStore();
        $threshold = 3;
        $recoveryTimeoutMs = 5000;

        $store->recordFailure();
        $store->recordFailure();
        $this->assertFalse($store->isCircuitOpen($threshold, $recoveryTimeoutMs));

        $store->recordFailure();
        $this->assertTrue($store->isCircuitOpen($threshold, $recoveryTimeoutMs));
    }

    public function test_resets_state_completely(): void
    {
        $store = new InMemoryStateStore();

        $store->recordFailure();
        $store->recordFailure();
        $store->recordFailure();
        $this->assertTrue($store->isCircuitOpen(3, 5000));

        $store->reset();

        $this->assertFalse($store->isCircuitOpen(3, 5000));
        $this->assertFalse($store->isHalfOpen(5000));
    }

    public function test_records_success_and_resets_failures_when_closed(): void
    {
        $store = new InMemoryStateStore();

        $store->recordFailure();
        $store->recordFailure();
        $this->assertFalse($store->isCircuitOpen(3, 5000));

        $store->recordSuccess();

        $store->recordFailure();
        $this->assertFalse($store->isCircuitOpen(3, 5000));
    }

    public function test_detects_half_open_state_after_recovery_timeout(): void
    {
        $store = new InMemoryStateStore();
        $threshold = 1;
        $recoveryTimeoutMs = 100;

        $store->recordFailure();
        $this->assertTrue($store->isCircuitOpen($threshold, $recoveryTimeoutMs));
        $this->assertFalse($store->isHalfOpen($recoveryTimeoutMs));

        usleep(150 * 1000);

        $this->assertTrue($store->isHalfOpen($recoveryTimeoutMs));
    }

    public function test_tracks_half_open_successes(): void
    {
        $store = new InMemoryStateStore();

        $store->recordFailure();
        $store->isCircuitOpen(1, 100);

        usleep(150 * 1000);

        $store->reportSuccessInHalfOpen();
        $store->reportSuccessInHalfOpen();

        $this->assertTrue($store->checkHalfOpenRecovery(2));
        $this->assertFalse($store->checkHalfOpenRecovery(3));
    }

    public function test_resets_half_open_successes_on_failure(): void
    {
        $store = new InMemoryStateStore();

        $store->recordFailure();
        $store->isCircuitOpen(1, 100);
        usleep(150 * 1000);

        $store->reportSuccessInHalfOpen();
        $this->assertTrue($store->checkHalfOpenRecovery(1));

        $store->recordFailure();
        $this->assertFalse($store->checkHalfOpenRecovery(1));
    }

    public function test_record_failure_reopens_circuit_when_in_half_open(): void
    {
        $store = new InMemoryStateStore();
        $store->recordFailure();
        $this->assertTrue($store->isCircuitOpen(1, 100));

        usleep(150 * 1000);
        $this->assertTrue($store->isHalfOpen(100));

        $store->recordFailure();
        $this->assertTrue($store->isCircuitOpen(1, 100));
    }
}
