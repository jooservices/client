<?php

declare(strict_types=1);

namespace JOOservices\Client\Resilience;

class CircuitBreakerConfig
{
    public function __construct(
        public readonly int $failureThreshold = 5,
        public readonly int $recoveryTimeoutMs = 10000, // Time to stay Open before Half-Open
        public readonly int $successThreshold = 2 // Successes needed in Half-Open to Close
    ) {
    }
}
