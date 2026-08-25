<?php

declare(strict_types=1);

namespace JOOservices\Client\Resilience\Contracts;

interface RateLimitStoreInterface
{
    public function attempt(string $key, int $limit, float $periodSeconds): bool;
}
