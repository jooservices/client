<?php

declare(strict_types=1);

namespace JOOservices\Client\Support;

use Psr\Http\Message\StreamInterface;
use RuntimeException;

final class StreamContents
{
    public function read(StreamInterface $stream): string
    {
        if (! $stream->isSeekable()) {
            return $stream->getContents();
        }

        $position = $stream->tell();
        $stream->rewind();

        try {
            return $stream->getContents();
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
}
