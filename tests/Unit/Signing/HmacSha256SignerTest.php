<?php

declare(strict_types=1);

namespace JOOservices\Client\Tests\Unit\Signing;

use Faker\Factory;
use JOOservices\Client\Signing\HmacSha256Signer;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

final class HmacSha256SignerTest extends TestCase
{
    #[Test]
    public function testSignsRequestBodyWhoseReportedSizeExceedsResponseLimit(): void
    {
        $body = Factory::create()->text(120);
        $key = Factory::create()->word();
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('getSize')->willReturn(104_857_601);
        $stream->method('isSeekable')->willReturn(true);
        $stream->method('tell')->willReturn(3);
        $stream->expects(self::once())->method('rewind');
        $stream->expects(self::once())->method('getContents')->willReturn($body);
        $stream->expects(self::once())->method('seek')->with(3);

        $request = (new Psr17Factory())
            ->createRequest('POST', 'https://api.example.test/signed-body')
            ->withBody($stream);

        $signed = (new HmacSha256Signer($key))->sign($request);
        $payload = $request->getMethod() . "\n" . (string) $request->getUri() . "\n" . $body;

        self::assertSame(hash_hmac('sha256', $payload, $key), $signed->getHeaderLine('X-Signature'));
    }
}
