<?php

declare(strict_types=1);

namespace JOOservices\Client\Resilience\Storage;

use JOOservices\Client\Resilience\Contracts\BulkheadStoreInterface;
use JOOservices\Client\Resilience\Contracts\StateStoreInterface;

final class Psr16BulkheadStore implements BulkheadStoreInterface
{
    public function __construct(private readonly StateStoreInterface $states)
    {
    }

    public function tryAcquire(string $key, int $limit): bool
    {
        $acquired = false;
        $this->states->mutate('bulkhead:' . $key, function (?array $state) use ($limit, &$acquired): array {
            $active = is_int($state['active'] ?? null) ? $state['active'] : 0;
            if ($active >= $limit) {
                return ['active' => $active];
            }

            $acquired = true;

            return ['active' => $active + 1];
        });

        return $acquired;
    }

    public function release(string $key): void
    {
        $this->states->mutate('bulkhead:' . $key, static function (?array $state): array {
            $active = is_int($state['active'] ?? null) ? $state['active'] : 0;

            return ['active' => max(0, $active - 1)];
        });
    }
}
