<?php

declare(strict_types=1);

namespace JOOservices\Client\Resilience\Storage;

use JOOservices\Client\Resilience\Contracts\BulkheadStoreInterface;

final class InMemoryBulkheadStore implements BulkheadStoreInterface
{
    /** @var array<string, int> */
    private array $active = [];

    public function tryAcquire(string $key, int $limit): bool
    {
        $current = $this->active[$key] ?? 0;
        if ($current >= $limit) {
            return false;
        }

        $this->active[$key] = $current + 1;

        return true;
    }

    public function release(string $key): void
    {
        $current = $this->active[$key] ?? 0;
        if ($current <= 1) {
            unset($this->active[$key]);

            return;
        }

        $this->active[$key] = $current - 1;
    }
}
