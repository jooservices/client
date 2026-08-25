<?php

declare(strict_types=1);

namespace JOOservices\Client\Resilience\Contracts;

interface BulkheadStoreInterface
{
    public function tryAcquire(string $key, int $limit): bool;

    public function release(string $key): void;
}
