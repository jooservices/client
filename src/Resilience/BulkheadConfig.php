<?php

declare(strict_types=1);

namespace JOOservices\Client\Resilience;

use JOOservices\Client\Exceptions\InvalidConfigurationException;
use JOOservices\Dto\Core\Dto;

final class BulkheadConfig extends Dto
{
    public function __construct(public readonly int $maxConcurrent = 10)
    {
        if ($maxConcurrent < 1) {
            throw new InvalidConfigurationException('BulkheadConfig maxConcurrent must be at least 1.');
        }
    }
}
