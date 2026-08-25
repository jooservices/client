<?php

declare(strict_types=1);

namespace JOOservices\Client\Cache;

use DateInterval;
use DateTimeImmutable;

final class ArrayCache
{
    /** Safety ceiling so a long-running process using the default cache can't grow this unboundedly. */
    private const DEFAULT_MAX_ENTRIES = 1000;

    /** @var array<string, array{value: mixed, expires: float|null}> */
    private array $items = [];

    public function __construct(private readonly int $maxEntries = self::DEFAULT_MAX_ENTRIES)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $item = $this->items[$key] ?? null;
        if ($item === null) {
            return $default;
        }

        if ($item['expires'] !== null && $item['expires'] <= microtime(true)) {
            unset($this->items[$key]);

            return $default;
        }

        return $item['value'];
    }

    public function set(string $key, mixed $value, int|DateInterval|null $ttl = null): bool
    {
        $expires = null;
        if ($ttl instanceof DateInterval) {
            $expires = (float) (new DateTimeImmutable())->add($ttl)->format('U.u');
        } elseif (is_int($ttl)) {
            $expires = microtime(true) + $ttl;
        }

        if (! array_key_exists($key, $this->items) && count($this->items) >= $this->maxEntries) {
            $oldest = array_key_first($this->items);
            if ($oldest !== null) {
                unset($this->items[$oldest]);
            }
        }

        $this->items[$key] = ['value' => $value, 'expires' => $expires];

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->items[$key]);

        return true;
    }
}
