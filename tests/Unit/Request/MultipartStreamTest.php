<?php

declare(strict_types=1);

namespace JOOservices\Client\Tests\Unit\Request;

use InvalidArgumentException;
use JOOservices\Client\Exceptions\InvalidConfigurationException;
use JOOservices\Client\Request\MultipartPart;
use JOOservices\Client\Request\MultipartStream;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MultipartStreamTest extends TestCase
{
    #[Test]
    public function testReadsInChunksAndReportsSize(): void
    {
        $stream = new MultipartStream([
            ['name' => 'title', 'contents' => 'Hello'],
            ['name' => 'file', 'contents' => 'bytes', 'filename' => 'a.txt', 'contentType' => 'text/plain'],
        ]);

        $assembled = '';
        while ($stream->eof() === false) {
            $assembled .= $stream->read(7);
        }

        self::assertSame($assembled, (string) $stream);
        self::assertSame(strlen($assembled), $stream->getSize());
        self::assertTrue($stream->isSeekable());
        self::assertTrue($stream->isReadable());
        self::assertFalse($stream->isWritable());
        self::assertSame([], $stream->getMetadata());
        self::assertNull($stream->getMetadata('timed_out'));
        self::assertStringContainsString('name="title"', $assembled);
        self::assertStringContainsString('filename="a.txt"', $assembled);
    }

    #[Test]
    public function testSeeksRelativeAndFromEnd(): void
    {
        $stream = new MultipartStream([['name' => 'title', 'contents' => 'Hello']]);
        $all = $stream->getContents();
        $stream->seek(2);
        self::assertSame(2, $stream->tell());
        $stream->seek(3, SEEK_CUR);
        self::assertSame(substr($all, 5), $stream->getContents());
        $stream->seek(-4, SEEK_END);
        self::assertSame(substr($all, -4), $stream->getContents());
    }

    #[Test]
    public function testWriteAndNegativeReadAreRejected(): void
    {
        $stream = new MultipartStream([['name' => 'title', 'contents' => 'Hello']]);
        $this->expectException(RuntimeException::class);
        $stream->write('nope');
    }

    #[Test]
    public function testNegativeReadLengthIsRejected(): void
    {
        $stream = new MultipartStream([['name' => 'title', 'contents' => 'Hello']]);
        $this->expectException(InvalidArgumentException::class);
        $stream->read(-1);
    }

    #[Test]
    public function testZeroLengthReadIsEmpty(): void
    {
        $stream = new MultipartStream([['name' => 'title', 'contents' => 'Hello']]);
        self::assertSame('', $stream->read(0));
    }

    #[Test]
    public function testInvalidSeekOriginIsRejected(): void
    {
        $stream = new MultipartStream([['name' => 'title', 'contents' => 'Hello']]);
        $this->expectException(InvalidArgumentException::class);
        $stream->seek(0, 99);
    }

    #[Test]
    public function testSeekPastTheEndIsRejected(): void
    {
        $stream = new MultipartStream([['name' => 'title', 'contents' => 'Hello']]);
        $this->expectException(RuntimeException::class);
        $stream->seek(($stream->getSize() ?? 0) + 1);
    }

    #[Test]
    public function testUnseekablePartsDisableSeek(): void
    {
        $inner = self::createStub(\Psr\Http\Message\StreamInterface::class);
        $inner->method('isSeekable')->willReturn(false);
        $inner->method('getSize')->willReturn(null);
        $inner->method('read')->willReturnOnConsecutiveCalls('pipe-bytes', '');
        $inner->method('eof')->willReturn(true);

        $stream = new MultipartStream([['name' => 'file', 'contents' => $inner, 'filename' => 'a.bin']]);
        self::assertFalse($stream->isSeekable());
        self::assertNull($stream->getSize());
        self::assertStringContainsString('pipe-bytes', $stream->getContents());

        $this->expectException(RuntimeException::class);
        $stream->rewind();
    }

    #[Test]
    public function testCloseDetachesTheStream(): void
    {
        $stream = new MultipartStream([['name' => 'title', 'contents' => 'Hello']]);
        self::assertNull($stream->detach());
        self::assertSame('', $stream->read(10));
        self::assertTrue($stream->eof());
    }

    #[Test]
    public function testExtraPartHeadersAreWritten(): void
    {
        $stream = new MultipartStream([
            new MultipartPart('file', 'x', 'a.txt', 'text/plain', ['X-Checksum' => 'abc']),
        ]);
        $body = $stream->getContents();
        self::assertStringContainsString('X-Checksum: abc', $body);
        self::assertStringContainsString('Content-Type: text/plain', $body);
    }

    #[Test]
    public function testRejectsNonArrayParts(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        new MultipartStream($this->parts(['title']));
    }

    #[Test]
    public function testRejectsAnEmptyFieldName(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        new MultipartStream($this->parts([['name' => '', 'contents' => 'x']]));
    }

    #[Test]
    public function testRejectsAPartWithoutAName(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        new MultipartStream($this->parts([['contents' => 'x']]));
    }

    #[Test]
    public function testRejectsAPartWithoutContents(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        new MultipartStream($this->parts([['name' => 'title']]));
    }

    #[Test]
    public function testRejectsANonStringFilename(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        new MultipartStream($this->parts([['name' => 'file', 'contents' => 'x', 'filename' => 1]]));
    }

    #[Test]
    public function testRejectsQuotedFilenames(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        new MultipartStream($this->parts([['name' => 'file', 'contents' => 'x', 'filename' => 'a"b.txt']]));
    }

    #[Test]
    public function testRejectsNonStringContents(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        new MultipartStream($this->parts([['name' => 'title', 'contents' => 123]]));
    }

    #[Test]
    public function testRejectsNonStringPartHeaders(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        new MultipartStream($this->parts([['name' => 'title', 'contents' => 'x', 'headers' => ['X-Test' => 1]]]));
    }

    #[Test]
    public function testRejectsNonArrayPartHeaders(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        new MultipartStream($this->parts([['name' => 'title', 'contents' => 'x', 'headers' => 'X-Test']]));
    }

    #[Test]
    public function testRejectsANonStringContentType(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        new MultipartStream($this->parts([['name' => 'file', 'contents' => 'x', 'contentType' => 1]]));
    }

    /**
     * @param list<mixed> $parts
     * @return list<MultipartPart|array<string, mixed>>
     */
    private function parts(array $parts): array
    {
        /** @var list<MultipartPart|array<string, mixed>> $parts */
        return $parts;
    }

    #[Test]
    public function testPsrStreamPartsAreRewindable(): void
    {
        $inner = (new Psr17Factory())->createStream('inner-file');
        $stream = new MultipartStream([['name' => 'file', 'contents' => $inner, 'filename' => 'a.bin']]);
        $first = $stream->getContents();
        $stream->rewind();
        self::assertSame($first, $stream->getContents());
        self::assertStringContainsString('inner-file', $first);
    }
}
