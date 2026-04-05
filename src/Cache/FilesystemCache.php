<?php

declare(strict_types=1);

namespace JOOservices\Client\Cache;

use DateInterval;
use DateTimeImmutable;
use Psr\SimpleCache\CacheInterface;
use RuntimeException;

class FilesystemCache implements CacheInterface
{
    private string $directory;

    public function __construct(string $directory)
    {
        $this->directory = rtrim($directory, '/\\');

        if (!is_dir($this->directory)) {
            if (!mkdir($this->directory, 0755, true) && !is_dir($this->directory)) {
                throw new RuntimeException("Directory '{$this->directory}' was not created");
            }
        }
    }

    private function getFilename(string $key): string
    {
        // Use SHA256 for better security
        return $this->directory . DIRECTORY_SEPARATOR . hash('sha256', $key) . '.cache';
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $filename = $this->getFilename($key);

        if (!file_exists($filename)) {
            return $default;
        }

        $data = $this->readCachePayload($filename);
        if ($data === null) {
            return $default;
        }

        $expiresAt = $this->parseExpiresAt($filename, $data['expiresAt']);
        if ($expiresAt === false) {
            return $default;
        }

        if ($expiresAt !== null && $expiresAt < new DateTimeImmutable()) {
            unlink($filename);
            return $default;
        }

        return $data['value'];
    }

    /**
     * @return array{expiresAt: mixed, value: mixed}|null
     */
    private function readCachePayload(string $filename): ?array
    {
        $content = file_get_contents($filename);
        if ($content === false) {
            return null;
        }

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            unlink($filename);

            return null;
        }

        if (!is_array($data) || !array_key_exists('expiresAt', $data) || !array_key_exists('value', $data)) {
            unlink($filename);

            return null;
        }

        return $data;
    }

    private function parseExpiresAt(string $filename, mixed $expiresAtRaw): DateTimeImmutable|false|null
    {
        if ($expiresAtRaw === null) {
            return null;
        }

        if (!is_string($expiresAtRaw) || $expiresAtRaw === '') {
            unlink($filename);

            return false;
        }

        try {
            return new DateTimeImmutable($expiresAtRaw);
        } catch (\Exception) {
            unlink($filename);

            return false;
        }
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

        $data = [
            'value' => $value,
            'expiresAt' => $expiresAt?->format(DateTimeImmutable::ATOM),
        ];

        $filename = $this->getFilename($key);
        try {
            $json = json_encode($data, JSON_THROW_ON_ERROR);
            return file_put_contents($filename, $json) !== false;
        } catch (\JsonException $e) {
            return false;
        }
    }

    public function delete(string $key): bool
    {
        $filename = $this->getFilename($key);
        if (file_exists($filename)) {
            return unlink($filename);
        }
        return true;
    }

    public function clear(): bool
    {
        $files = glob($this->directory . DIRECTORY_SEPARATOR . '*.cache');
        if ($files === false) {
            return false;
        }

        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }
        return $result;
    }

    /**
     * @param iterable<array-key, mixed> $values
     */
    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set((string) $key, $value, $ttl);
        }
        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }
        return true;
    }

    public function has(string $key): bool
    {
        return $this->get($key, $this) !== $this;
    }
}
