<?php

declare(strict_types=1);

namespace JOOservices\Client\Resilience\Storage;

use JOOservices\Client\Resilience\Contracts\RateLimitStoreInterface;

final class InMemoryRateLimitStore implements RateLimitStoreInterface
{
    /** @var array<string, list<float>> */
    private array $requests = [];

    public function attempt(string $key, int $limit, float $periodSeconds): bool
    {
        $now = microtime(true);
        $cutoff = $now - $periodSeconds;
        $recent = array_values(array_filter($this->requests[$key] ?? [], static fn(float $time): bool => $time > $cutoff));
        if (count($recent) >= $limit) {
            $this->requests[$key] = $recent;

            return false;
        }

        $recent[] = $now;
        $this->requests[$key] = $recent;
        $this->pruneStaleKeys($cutoff);

        return true;
    }

    /**
     * A key whose window has fully expired since it was last touched otherwise sits in $this->requests
     * forever with an empty list — unbounded growth for a long-running process using a high-cardinality
     * partition key (per-user, per-IP, ...) instead of the default per-host one.
     */
    private function pruneStaleKeys(float $cutoff): void
    {
        foreach ($this->requests as $key => $times) {
            if ($times === [] || max($times) <= $cutoff) {
                unset($this->requests[$key]);
            }
        }
    }
}
