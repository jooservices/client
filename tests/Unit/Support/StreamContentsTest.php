<?php

declare(strict_types=1);

namespace JOOservices\Client\Tests\Unit\Support;

use JOOservices\Client\Support\StreamContents;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StreamContentsTest extends TestCase
{
    #[Test]
    public function testReadingPreservesTheOriginalCursor(): void
    {
        $stream = (new Psr17Factory())->createStream('abcdef');
        $stream->seek(3);

        self::assertSame('abcdef', (new StreamContents())->read($stream));
        self::assertSame(3, $stream->tell());
        self::assertSame('def', $stream->getContents());
    }

    #[Test]
    public function testCopyToResourceRestoresCursorAndSpillsToTemp(): void
    {
        $stream = (new Psr17Factory())->createStream(str_repeat('x', 4096));
        $stream->seek(10);
        $resource = (new StreamContents())->copyToResource($stream);
        self::assertSame(10, $stream->tell());
        self::assertSame(str_repeat('x', 4096), stream_get_contents($resource));
        fclose($resource);
    }
}
