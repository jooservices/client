<?php

declare(strict_types=1);

namespace JOOservices\Client\Resilience;

use JOOservices\Client\Exceptions\NetworkConnectionException;
use Throwable;

class RetryConfig
{
    /**
     * @param int $maxAttempts
     * @param int $baseDelayMs
     * @param int $maxDelayMs
     * @param bool $useJitter
     * @param int[] $retryableStatuses
     * @param string[] $retryableMethods
     * @param class-string<Throwable>[] $retryableExceptions
     */
    public function __construct(
        public readonly int $maxAttempts = 3,
        public readonly int $baseDelayMs = 100,
        public readonly int $maxDelayMs = 2000,
        public readonly bool $useJitter = true,
        public readonly array $retryableStatuses = [429, 500, 502, 503, 504],
        public readonly array $retryableMethods = ['GET', 'HEAD', 'OPTIONS', 'PUT', 'DELETE'],
        public readonly array $retryableExceptions = [NetworkConnectionException::class]
    ) {
    }
}
