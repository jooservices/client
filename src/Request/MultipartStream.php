<?php

declare(strict_types=1);

namespace JOOservices\Client\Request;

use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Throwable;

/**
 * Read-only multipart/form-data body. File contents are read only as the
 * HTTP client consumes the stream, and the body is rewindable when every
 * part is rewindable.
 */
final class MultipartStream implements StreamInterface
{
    private readonly string $boundary;

    /** @var list<string|resource|StreamInterface> */
    private array $chunks;

    private int $index = 0;

    private int $count;

    private string $buffer = '';

    private int $position = 0;

    private readonly bool $seekable;

    private readonly ?int $size;

    /**
     * @param list<MultipartPart|array<string, mixed>> $parts
     */
    public function __construct(array $parts)
    {
        $body = new MultipartBody($parts);
        $this->boundary = $body->boundary;
        $this->chunks = $body->chunks;
        $this->count = count($body->chunks);
        $this->seekable = $body->seekable;
        $this->size = $body->size;
    }

    public function boundary(): string
    {
        return $this->boundary;
    }

    public function __toString(): string
    {
        try {
            if ($this->isSeekable()) {
                $this->rewind();
            }

            return $this->getContents();
        } catch (Throwable) {
            return '';
        }
    }

    public function close(): void
    {
        $this->chunks = [];
        $this->buffer = '';
        $this->index = 0;
        $this->count = 0;
    }

    public function detach()
    {
        $this->close();

        return null;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function tell(): int
    {
        return $this->position;
    }

    public function eof(): bool
    {
        return $this->buffer === '' && $this->index >= $this->count;
    }

    public function isSeekable(): bool
    {
        return $this->seekable;
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        if ($this->seekable === false) {
            throw new RuntimeException('Multipart upload stream is not seekable.');
        }

        $target = $this->targetOffset($offset, $whence);
        if ($target < 0 || ($this->size !== null && $target > $this->size)) {
            throw new RuntimeException('Unable to seek to the requested multipart stream position.');
        }

        $this->reset();
        $remaining = $target;
        while ($remaining > 0) {
            $chunk = $this->read(min(8192, $remaining));
            if ($chunk === '') {
                throw new RuntimeException('Unable to seek to the requested multipart stream position.');
            }

            $remaining -= strlen($chunk);
        }
    }

    public function rewind(): void
    {
        $this->seek(0);
    }

    public function isWritable(): bool
    {
        return false;
    }

    public function write(string $string): int
    {
        unset($string);

        throw new RuntimeException('Multipart upload streams are read-only.');
    }

    public function isReadable(): bool
    {
        return true;
    }

    public function read(int $length): string
    {
        if ($length < 0) {
            throw new InvalidArgumentException('Read length must be a non-negative integer.');
        }

        if ($length === 0 || $this->eof()) {
            return '';
        }

        $this->fill($length);
        $result = substr($this->buffer, 0, $length);
        $this->buffer = substr($this->buffer, strlen($result));
        $this->position += strlen($result);

        return $result;
    }

    public function getContents(): string
    {
        $contents = '';
        while ($this->eof() === false) {
            $contents .= $this->read(8192);
        }

        return $contents;
    }

    public function getMetadata(?string $key = null)
    {
        return $key === null ? [] : null;
    }

    private function targetOffset(int $offset, int $whence): int
    {
        return match ($whence) {
            SEEK_SET => $offset,
            SEEK_CUR => $this->position + $offset,
            SEEK_END => $this->size === null
                ? throw new RuntimeException('Unable to seek to the requested multipart stream position.')
                : $this->size + $offset,
            default => throw new InvalidArgumentException('Invalid seek origin.'),
        };
    }

    private function fill(int $length): void
    {
        while (strlen($this->buffer) < $length && $this->index < $this->count) {
            $current = $this->chunks[$this->index];
            if (is_string($current)) {
                $this->buffer .= $current;
                ++$this->index;

                continue;
            }

            $this->appendChunk($current, $length);
        }
    }

    /** @param resource|StreamInterface $current */
    private function appendChunk(mixed $current, int $length): void
    {
        $needed = max(1, $length - strlen($this->buffer));
        $chunk = $current instanceof StreamInterface ? $current->read($needed) : fread($current, $needed);
        if ($chunk === false) {
            throw new RuntimeException('Unable to read the multipart upload contents.');
        }

        if ($chunk !== '') {
            $this->buffer .= $chunk;

            return;
        }

        if ($current instanceof StreamInterface && $current->eof() === false) {
            throw new RuntimeException('Unable to read the multipart upload contents.');
        }

        ++$this->index;
    }

    private function reset(): void
    {
        foreach ($this->chunks as $chunk) {
            $this->rewindChunk($chunk);
        }

        $this->index = 0;
        $this->buffer = '';
        $this->position = 0;
    }

    private function rewindChunk(mixed $chunk): void
    {
        if (is_string($chunk)) {
            return;
        }

        if ($chunk instanceof StreamInterface) {
            $chunk->rewind();

            return;
        }

        if (! is_resource($chunk) || rewind($chunk) === false) {
            throw new RuntimeException('Unable to rewind the multipart upload contents.');
        }
    }
}
