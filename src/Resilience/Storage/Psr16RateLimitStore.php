<?php

declare(strict_types=1);

namespace JOOservices\Client\Resilience\Storage;

use JOOservices\Client\Resilience\Contracts\RateLimitStoreInterface;
use JOOservices\Client\Resilience\Contracts\StateStoreInterface;

final class Psr16RateLimitStore implements RateLimitStoreInterface
{
    public function __construct(private readonly StateStoreInterface $states)
    {
    }

    public function attempt(string $key, int $limit, float $periodSeconds): bool
    {
        $now = microtime(true);
        $allowed = false;
        $this->states->mutate('rate-limit:' . $key, function (?array $state) use ($limit, $periodSeconds, $now, &$allowed): array {
            $times = is_array($state['times'] ?? null) ? $state['times'] : [];
            $recent = array_values(array_filter($times, static fn(mixed $time): bool => is_float($time) && $time > $now - $periodSeconds));

            if (count($recent) >= $limit) {
                return ['times' => $recent];
            }

            $recent[] = $now;
            $allowed = true;

            return ['times' => $recent];
        }, (int) ceil($periodSeconds));

        return $allowed;
    }
}
