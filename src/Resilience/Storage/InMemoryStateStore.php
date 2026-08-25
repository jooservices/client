<?php

declare(strict_types=1);

namespace JOOservices\Client\Resilience\Storage;

use JOOservices\Client\Resilience\Contracts\StateStoreInterface;

final class InMemoryStateStore implements StateStoreInterface
{
    /** @var array<string, array<string, mixed>> */
    private array $states = [];

    public function get(string $key): ?array
    {
        return $this->states[$key] ?? null;
    }

    public function put(string $key, array $state, ?int $ttlSeconds = null): void
    {
        unset($ttlSeconds); // No expiry mechanism here — state lives for the process lifetime, same as before.
        $this->states[$key] = $state;
    }

    public function forget(string $key): void
    {
        unset($this->states[$key]);
    }

    public function mutate(string $key, callable $mutator, ?int $ttlSeconds = null): array
    {
        $state = $mutator($this->get($key));
        $this->put($key, $state, $ttlSeconds);

        return $state;
    }
}
