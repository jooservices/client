<?php

declare(strict_types=1);

namespace JOOservices\Client\Resilience\Contracts;

interface StateStoreInterface
{
    public function recordFailure(): void;
    public function recordSuccess(): void;
    public function isCircuitOpen(int $failureThreshold, int $recoveryTimeoutMs): bool;
    public function isHalfOpen(int $recoveryTimeoutMs): bool;
    public function reportSuccessInHalfOpen(): void;
    public function checkHalfOpenRecovery(int $successThreshold): bool;

    /**
     * Reset the state to CLOSED (healthy).
     */
    public function reset(): void;
}
