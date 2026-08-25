<?php

declare(strict_types=1);

namespace JOOservices\Client\Resilience\Contracts;

interface StateStoreInterface
{
    /** @return array<string, mixed>|null */
    public function get(string $key): ?array;

    /**
     * @param array<string, mixed> $state
     * @param int|null $ttlSeconds Override the store's default TTL for this entry — a caller tracking a
     *   time-bounded window (a rate-limit period, a circuit-breaker reset window) should pass that
     *   window so the entry can't expire and silently reset the count before the window is actually
     *   over. Null keeps the store's own default.
     */
    public function put(string $key, array $state, ?int $ttlSeconds = null): void;

    public function forget(string $key): void;

    /**
     * Read-modify-write $key: applies $mutator to the current state (or null when absent) and stores
     * the result. An implementation backed by a real lock/CAS primitive would guarantee no other
     * caller's mutate() on the same key interleaves between the read and the write; a plain PSR-16
     * cache cannot provide that guarantee (no compare-and-swap in the PSR-16 contract), so
     * Psr16StateStore documents its mutate() as best-effort only. InMemoryStateStore's is exact,
     * since this library has no real intra-process concurrency to race against.
     *
     * @param callable(array<string, mixed>|null): array<string, mixed> $mutator
     * @param int|null $ttlSeconds See put().
     * @return array<string, mixed>
     */
    public function mutate(string $key, callable $mutator, ?int $ttlSeconds = null): array;
}
