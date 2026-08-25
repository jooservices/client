<?php

declare(strict_types=1);

namespace JOOservices\Client\Resilience;

use JOOservices\Client\Exceptions\InvalidConfigurationException;
use JOOservices\Dto\Core\Dto;

final class RetryConfig extends Dto
{
    /**
     * @param list<int> $statuses
     * @param list<string> $methods
     */
    public function __construct(
        public readonly int $maxAttempts = 3,
        public readonly int $delayMilliseconds = 100,
        public readonly array $statuses = [408, 425, 429, 500, 502, 503, 504],
        public readonly array $methods = ['GET', 'HEAD', 'PUT', 'DELETE', 'OPTIONS'],
    ) {
        if ($maxAttempts < 1) {
            throw new InvalidConfigurationException('RetryConfig maxAttempts must be at least 1.');
        }

        if ($delayMilliseconds < 0) {
            throw new InvalidConfigurationException('RetryConfig delayMilliseconds must not be negative.');
        }
    }
}
