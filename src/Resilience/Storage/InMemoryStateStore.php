<?php

declare(strict_types=1);

namespace JOOservices\Client\Resilience\Storage;

use JOOservices\Client\Resilience\Contracts\StateStoreInterface;

class InMemoryStateStore implements StateStoreInterface
{
    private int $failures = 0;
    private ?float $lastFailureTime = null;
    private ?float $openedAt = null;
    private int $halfOpenSuccesses = 0;

    public function recordFailure(): void
    {
        $this->failures++;
        $this->lastFailureTime = microtime(true);
        $this->halfOpenSuccesses = 0;
    }

    public function recordSuccess(): void
    {
        // If closed, simple reset.
        if ($this->openedAt === null) {
            $this->failures = 0;
            return;
        }

        // If Open/Half-Open, success means a probe worked.
        $this->halfOpenSuccesses++;
    }

    public function isCircuitOpen(int $failureThreshold, int $recoveryTimeoutMs): bool
    {
        // If explicitly opened
        if ($this->openedAt !== null) {
            // Check if we passed recovery time (Moved to Half-Open?)
            $elapsedMs = (microtime(true) - $this->openedAt) * 1000;
            if ($elapsedMs > $recoveryTimeoutMs) {
                return false; // Actually it's Half-Open now, not Open
            }
            return true;
        }

        // Check threshold
        if ($this->failures >= $failureThreshold) {
            $this->openedAt = microtime(true);
            return true;
        }

        return false;
    }

    public function isHalfOpen(int $recoveryTimeoutMs): bool
    {
        if ($this->openedAt === null) {
            return false;
        }

        $elapsedMs = (microtime(true) - $this->openedAt) * 1000;
        return $elapsedMs > $recoveryTimeoutMs;
    }

    public function reportSuccessInHalfOpen(): void
    {
        $this->halfOpenSuccesses++;
    }

    public function checkHalfOpenRecovery(int $successThreshold): bool
    {
        return $this->halfOpenSuccesses >= $successThreshold;
    }

    public function reset(): void
    {
        $this->failures = 0;
        $this->lastFailureTime = null;
        $this->openedAt = null;
        $this->halfOpenSuccesses = 0;
    }

    public function getFailureCount(): int
    {
        return $this->failures;
    }

    public function getLastFailureTime(): ?float
    {
        return $this->lastFailureTime;
    }
}
