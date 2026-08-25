<?php

declare(strict_types=1);

namespace JOOservices\Client\Resilience;

use JOOservices\Client\Exceptions\InvalidConfigurationException;
use JOOservices\Dto\Core\Dto;

final class RateLimitConfig extends Dto
{
    public function __construct(public readonly int $maxRequests = 60, public readonly float $perSeconds = 60.0)
    {
        if ($maxRequests < 1) {
            throw new InvalidConfigurationException('RateLimitConfig maxRequests must be at least 1.');
        }

        if ($perSeconds <= 0.0) {
            throw new InvalidConfigurationException('RateLimitConfig perSeconds must be greater than zero.');
        }
    }
}
