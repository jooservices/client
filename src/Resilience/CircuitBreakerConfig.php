<?php

declare(strict_types=1);

namespace JOOservices\Client\Resilience;

use JOOservices\Client\Exceptions\InvalidConfigurationException;
use JOOservices\Dto\Core\Dto;

final class CircuitBreakerConfig extends Dto
{
    /** @param list<int> $failureStatuses */
    public function __construct(
        public readonly int $failureThreshold = 5,
        public readonly float $resetAfterSeconds = 30.0,
        public readonly array $failureStatuses = [500, 502, 503, 504],
    ) {
        if ($failureThreshold < 1) {
            throw new InvalidConfigurationException('CircuitBreakerConfig failureThreshold must be at least 1.');
        }

        if ($resetAfterSeconds <= 0.0) {
            throw new InvalidConfigurationException('CircuitBreakerConfig resetAfterSeconds must be greater than zero.');
        }
    }
}
