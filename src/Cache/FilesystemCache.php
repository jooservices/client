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

        $content = file_get_contents($filename);
        if ($content === false) {
            return $default;
        }

        // Decode JSON data
        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            // JSON decode failed - corrupted cache
            unlink($filename);
            return $default;
        }

        if (!is_array($data) || !isset($data['expiresAt'], $data['value'])) {
            unlink($filename);
            return $default;
        }

        // Reconstruct DateTimeImmutable from ISO 8601 string
        $expiresAt = null;
        if ($data['expiresAt'] !== null) {
            try {
                $expiresAt = new DateTimeImmutable($data['expiresAt']);
            } catch (\Exception $e) {
                // Invalid date format
                unlink($filename);
                return $default;
            }
        }

        if ($expiresAt !== null && $expiresAt < new DateTimeImmutable()) {
            unlink($filename);
            return $default;
        }

        return $data['value'];
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
