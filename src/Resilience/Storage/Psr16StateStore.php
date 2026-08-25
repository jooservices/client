<?php

declare(strict_types=1);

namespace JOOservices\Client\Resilience\Storage;

use JOOservices\Client\Exceptions\InvalidConfigurationException;
use JOOservices\Client\Resilience\Contracts\StateStoreInterface;

final class Psr16StateStore implements StateStoreInterface
{
    public function __construct(private readonly object $cache, private readonly int $ttl = 3600)
    {
        foreach (['get', 'set', 'delete'] as $method) {
            if (! method_exists($cache, $method)) {
                throw new InvalidConfigurationException('PSR-16 cache must implement get(), set(), and delete().');
            }
        }
    }

    public function get(string $key): ?array
    {
        $getter = $this->method('get');
        $value = $getter($key);

        if (! is_array($value)) {
            return null;
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    public function put(string $key, array $state, ?int $ttlSeconds = null): void
    {
        $setter = $this->method('set');
        $setter($key, $state, $ttlSeconds ?? $this->ttl);
    }

    public function forget(string $key): void
    {
        $deleter = $this->method('delete');
        $deleter($key);
    }

    /**
     * Best-effort only: PSR-16 has no compare-and-swap, so this is a plain get() then set() with no
     * protection against another process's concurrent mutate() on the same key interleaving between
     * the two. Under concurrent writers sharing this cache (e.g. multiple PHP-FPM workers against
     * Redis/APCu), updates can be lost.
     */
    public function mutate(string $key, callable $mutator, ?int $ttlSeconds = null): array
    {
        $state = $mutator($this->get($key));
        $this->put($key, $state, $ttlSeconds);

        return $state;
    }

    /** @return callable(mixed...): mixed */
    private function method(string $name): callable
    {
        $method = [$this->cache, $name];
        if (! is_callable($method)) {
            throw new InvalidConfigurationException(sprintf('PSR-16 cache method %s() is not callable.', $name));
        }

        return $method;
    }
}
