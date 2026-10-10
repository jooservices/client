<?php

declare(strict_types=1);

namespace JOOservices\Client\Tests\Unit\Support;

use Faker\Factory;
use JOOservices\Client\Exceptions\DownloadSizeExceededException;
use JOOservices\Client\Support\StreamContents;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

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
    public function testReadingResponseBodyPreservesTheOriginalCursor(): void
    {
        $body = Factory::create()->text(120);
        $stream = (new Psr17Factory())->createStream($body);
        $stream->seek(10);

        self::assertSame($body, (new StreamContents())->readResponseBody($stream));
        self::assertSame(10, $stream->tell());
        self::assertSame(substr($body, 10), $stream->getContents());
    }

    #[Test]
    public function testKnownOversizedStreamIsRejectedBeforeReading(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('getSize')->willReturn(104_857_601);
        $stream->expects(self::never())->method('read');
        $stream->expects(self::never())->method('tell');

        $this->expectException(DownloadSizeExceededException::class);
        (new StreamContents())->readResponseBody($stream);
    }

    #[Test]
    public function testValidOversizedContentLengthIsRejectedBeforeReading(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('getSize')->willReturn(null);
        $stream->expects(self::never())->method('read');

        $this->expectException(DownloadSizeExceededException::class);
        (new StreamContents())->readResponseBody($stream, '104857601');
    }

    #[Test]
    public function testInvalidContentLengthDoesNotPreventReadingSmallBody(): void
    {
        $body = Factory::create()->text(120);
        $stream = (new Psr17Factory())->createStream($body);

        self::assertSame($body, (new StreamContents())->readResponseBody($stream, '104857601 invalid'));
    }

    #[Test]
    public function testUnderreportedLengthStopsAtTheLimitAndRestoresCursorOnFailure(): void
    {
        [$stream, $getState] = $this->unknownLengthSeekableStream(7);

        try {
            (new StreamContents())->readResponseBody($stream, '1');
            self::fail('Expected an oversized stream to be rejected.');
        } catch (DownloadSizeExceededException) {
            /** @var array{position: int, bytesRead: int, maxReadLength: int} $state */
            $state = $getState();
            self::assertSame(7, $state['position']);
            self::assertSame(104_857_601, $state['bytesRead']);
            self::assertLessThanOrEqual(8192, $state['maxReadLength']);
        }
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

    /** @return array{StreamInterface, \Closure(): array{position: int, bytesRead: int, maxReadLength: int}} */
    private function unknownLengthSeekableStream(int $initialPosition): array
    {
        /** @var array{position: int, bytesRead: int, maxReadLength: int} $state */
        $state = ['position' => $initialPosition, 'bytesRead' => 0, 'maxReadLength' => 0];
        $fill = Factory::create()->randomLetter();
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('getSize')->willReturn(null);
        $stream->method('isSeekable')->willReturn(true);
        $stream->expects(self::never())->method('getContents');
        $stream->method('tell')->willReturnCallback(static function () use (&$state): int {
            return $state['position'];
        });
        $stream->method('rewind')->willReturnCallback(static function () use (&$state): void {
            $state['position'] = 0;
        });
        $stream->method('seek')->willReturnCallback(static function (int $offset) use (&$state): void {
            $state['position'] = $offset;
        });
        $stream->method('eof')->willReturn(false);
        $stream->method('read')->willReturnCallback(static function (int $length) use ($fill, &$state): string {
            $state['bytesRead'] += $length;
            $state['position'] += $length;
            $state['maxReadLength'] = max($state['maxReadLength'], $length);

            return str_repeat($fill, $length);
        });

        $getState = static function () use (&$state): array {
            /** @var array{position: int, bytesRead: int, maxReadLength: int} $snapshot */
            $snapshot = [
                'position' => $state['position'],
                'bytesRead' => $state['bytesRead'],
                'maxReadLength' => $state['maxReadLength'],
            ];

            return $snapshot;
        };

        return [$stream, $getState];
    }
}
