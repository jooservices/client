<?php

declare(strict_types=1);

namespace JOOservices\Client\Support;

use JOOservices\Client\Exceptions\DownloadSizeExceededException;
use Closure;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

final class StreamContents
{
    private const MAX_BODY_BYTES = 104_857_600;

    private const READ_CHUNK_BYTES = 8192;

    public function read(StreamInterface $stream): string
    {
        return $this->readPreservingCursor($stream, static fn(): string => $stream->getContents());
    }

    public function readResponseBody(StreamInterface $stream, ?string $contentLength = null): string
    {
        $size = $stream->getSize();
        if (($size !== null && $size > self::MAX_BODY_BYTES) || $this->contentLengthExceedsLimit($contentLength)) {
            throw new DownloadSizeExceededException(sprintf('Response body exceeds the %d byte limit.', self::MAX_BODY_BYTES));
        }

        return $this->readPreservingCursor($stream, fn(): string => $this->readBounded($stream));
    }

    /** @param Closure(): string $reader */
    private function readPreservingCursor(StreamInterface $stream, Closure $reader): string
    {
        if (! $stream->isSeekable()) {
            return $reader();
        }

        $position = $stream->tell();

        try {
            $stream->rewind();

            return $reader();
        } finally {
            $stream->seek($position);
        }
    }

    /** @return resource */
    public function copyToResource(StreamInterface $stream)
    {
        $resource = fopen('php://temp/maxmemory:2097152', 'w+b');
        if ($resource === false) {
            throw new RuntimeException('Unable to allocate a temporary stream.');
        }

        $position = 0;
        $seekable = $stream->isSeekable();
        if ($seekable) {
            $position = $stream->tell();
            $stream->rewind();
        }

        try {
            while (! $stream->eof()) {
                $chunk = $stream->read(8192);
                if ($chunk === '') {
                    break;
                }

                fwrite($resource, $chunk);
            }
        } finally {
            if ($seekable) {
                $stream->seek($position);
            }
        }

        rewind($resource);

        return $resource;
    }

    private function readBounded(StreamInterface $stream): string
    {
        $contents = '';
        $bytesRead = 0;

        while (! $stream->eof()) {
            $remaining = self::MAX_BODY_BYTES - $bytesRead;
            $chunk = $stream->read(min(self::READ_CHUNK_BYTES, $remaining + 1));
            if ($chunk === '') {
                break;
            }

            $chunkBytes = strlen($chunk);
            if ($chunkBytes > $remaining) {
                throw new DownloadSizeExceededException(sprintf('Response body exceeds the %d byte limit.', self::MAX_BODY_BYTES));
            }

            $contents .= $chunk;
            $bytesRead += $chunkBytes;
        }

        return $contents;
    }

    private function contentLengthExceedsLimit(?string $contentLength): bool
    {
        if ($contentLength === null || trim($contentLength) === '') {
            return false;
        }

        $normalizedLength = null;
        foreach (explode(',', $contentLength) as $value) {
            $value = trim($value, " \t");
            if (preg_match('/\A[0-9]+\z/', $value) !== 1) {
                return false;
            }

            $value = ltrim($value, '0');
            if ($normalizedLength !== null && $normalizedLength !== $value) {
                return false;
            }

            $normalizedLength = $value;
        }

        return $this->decimalLengthExceedsLimit($normalizedLength, (string) self::MAX_BODY_BYTES);
    }

    private function decimalLengthExceedsLimit(string $value, string $limit): bool
    {
        if ($value === '') {
            return false;
        }

        return strlen($value) > strlen($limit)
            || (strlen($value) === strlen($limit) && strcmp($value, $limit) > 0);
    }
}
