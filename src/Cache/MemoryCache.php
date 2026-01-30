<?php

declare(strict_types=1);

namespace JOOservices\Client\Cache;

use DateInterval;
use DateTimeImmutable;
use Psr\SimpleCache\CacheInterface;

class MemoryCache implements CacheInterface
{
    /** @var array<string, array{value: mixed, expiresAt: ?DateTimeImmutable}> */
    private array $storage = [];

    public function get(string $key, mixed $default = null): mixed
    {
        if (!isset($this->storage[$key])) {
            return $default;
        }

        $item = $this->storage[$key];

        if ($item['expiresAt'] !== null && $item['expiresAt'] < new DateTimeImmutable()) {
            unset($this->storage[$key]);
            return $default;
        }

        return $item['value'];
    }

    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        $expiresAt = null;
        if ($ttl !== null) {
            $now = new DateTimeImmutable();
            if ($ttl instanceof DateInterval) {
                $expiresAt = $now->add($ttl);
            } elseif (is_int($ttl)) {
                $expiresAt = $now->modify(sprintf('+%d seconds', $ttl));
            }
        }

        $this->storage[$key] = [
            'value' => $value,
            'expiresAt' => $expiresAt,
        ];

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->storage[$key]);
        return true;
    }

    public function clear(): bool
    {
        $this->storage = [];
        return true;
    }

    /**
     * @param iterable<string> $keys
     * @param mixed $default
     * @return iterable<string, mixed>
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }
        return $result;
    }

    /**
     * @param iterable<string, mixed> $values
     * @param int|DateInterval|null $ttl
     */
    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set((string) $key, $value, $ttl);
        }
        return true;
    }

    /**
     * @param iterable<string> $keys
     */
    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }
        return true;
    }

    public function has(string $key): bool
    {
        return $this->get($key, $this) !== $this; // Trick to check existence vs default null
    }
}
