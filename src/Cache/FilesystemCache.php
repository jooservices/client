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
        // Simple hash to avoid invalid chars
        return $this->directory . DIRECTORY_SEPARATOR . sha1($key) . '.cache';
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

        // Suppress warnings from unserializing corrupted data
        $data = false;
        set_error_handler(fn() => true);
        try {
            $data = unserialize($content, ['allowed_classes' => true]);
        } catch (\Throwable $e) {
            // Unserialize failed
        } finally {
            restore_error_handler();
        }

        if (!is_array($data) || !isset($data['expiresAt'], $data['value'])) {
            unlink($filename);
            return $default;
        }

        /** @var DateTimeImmutable|null $expiresAt */
        $expiresAt = $data['expiresAt'];

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
            'expiresAt' => $expiresAt,
        ];

        $filename = $this->getFilename($key);
        return file_put_contents($filename, serialize($data)) !== false;
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
