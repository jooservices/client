<?php

declare(strict_types=1);

namespace JOOservices\Client\Tests\Unit\Middleware;

use Faker\Factory;
use JOOservices\Client\Cache\ArrayCache;
use JOOservices\Client\Dto\CacheConfig;
use JOOservices\Client\Dto\RequestOptions;
use JOOservices\Client\Middleware\CacheMiddleware;
use JOOservices\Client\Testing\FakeTransport;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

final class CacheMiddlewareTest extends TestCase
{
    #[Test]
    public function testRangeRequestBypassesCachedResponseAndDoesNotReplaceIt(): void
    {
        $factory = new Psr17Factory();
        $cache = new ArrayCache();
        $middleware = new CacheMiddleware($factory, $factory, new CacheConfig(60), $cache);
        $request = $factory->createRequest('GET', 'https://api.example.test/document');
        $fullBody = Factory::create()->text(120);
        $rangeBody = substr($fullBody, 0, 8);

        $middleware->process($request, new RequestOptions(), (new FakeTransport())->push(new Response(200, [], $fullBody)));

        $rangeOrigin = (new FakeTransport())->push(new Response(206, [], $rangeBody));
        $rangeResponse = $middleware->process(
            $request->withHeader('Range', 'bytes=0-7'),
            new RequestOptions(),
            $rangeOrigin,
        );

        self::assertSame(206, $rangeResponse->getStatusCode());
        self::assertSame($rangeBody, (string) $rangeResponse->getBody());
        self::assertCount(1, $rangeOrigin->recorded());

        $plainOrigin = (new FakeTransport())->push(new Response(200, [], Factory::create()->text(120)));
        $plainResponse = $middleware->process($request, new RequestOptions(), $plainOrigin);

        self::assertSame($fullBody, (string) $plainResponse->getBody());
        self::assertCount(0, $plainOrigin->recorded());
    }

    #[Test]
    public function testIfRangeRequestBypassesCachedResponseAndDoesNotReplaceIt(): void
    {
        $factory = new Psr17Factory();
        $cache = new ArrayCache();
        $middleware = new CacheMiddleware($factory, $factory, new CacheConfig(60), $cache);
        $request = $factory->createRequest('GET', 'https://api.example.test/document');
        $fullBody = Factory::create()->text(120);
        $rangeBody = substr($fullBody, 0, 8);

        $middleware->process($request, new RequestOptions(), (new FakeTransport())->push(new Response(200, [], $fullBody)));

        $rangeOrigin = (new FakeTransport())->push(new Response(206, [], $rangeBody));
        $rangeResponse = $middleware->process(
            $request->withHeader('If-Range', '"' . Factory::create()->word() . '"'),
            new RequestOptions(),
            $rangeOrigin,
        );

        self::assertSame(206, $rangeResponse->getStatusCode());
        self::assertSame($rangeBody, (string) $rangeResponse->getBody());
        self::assertCount(1, $rangeOrigin->recorded());

        $plainOrigin = (new FakeTransport())->push(new Response(200, [], Factory::create()->text(120)));
        $plainResponse = $middleware->process($request, new RequestOptions(), $plainOrigin);

        self::assertSame($fullBody, (string) $plainResponse->getBody());
        self::assertCount(0, $plainOrigin->recorded());
    }

    #[Test]
    public function testPlainGetTreatsCachedPartialResponseAsAMiss(): void
    {
        $factory = new Psr17Factory();
        $cache = new ArrayCache();
        $request = $factory->createRequest('GET', 'https://api.example.test/document');
        $partialBody = Factory::create()->text(60);
        $key = 'http:' . hash('sha256', (string) $request->getUri() . "\n");
        $cache->set($key, [
            'status' => 206,
            'headers' => ['Content-Range' => ['bytes 0-59/120']],
            'body' => $partialBody,
            'varyValues' => [],
        ], 60);

        $middleware = new CacheMiddleware($factory, $factory, new CacheConfig(60), $cache);
        $fullBody = Factory::create()->text(140);
        $origin = (new FakeTransport())->push(new Response(200, [], $fullBody));
        $response = $middleware->process($request, new RequestOptions(), $origin);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame($fullBody, (string) $response->getBody());
        self::assertCount(1, $origin->recorded());
    }

    #[Test]
    public function testDoesNotStorePartialResponseFromPlainGet(): void
    {
        $factory = new Psr17Factory();
        $middleware = new CacheMiddleware($factory, $factory, new CacheConfig(60), new ArrayCache());
        $request = $factory->createRequest('GET', 'https://api.example.test/document');
        $partialBody = Factory::create()->text(60);
        $fullBody = Factory::create()->text(140);
        $origin = (new FakeTransport())
            ->push(new Response(206, [], $partialBody))
            ->push(new Response(200, [], $fullBody));

        $partialResponse = $middleware->process($request, new RequestOptions(), $origin);
        $fullResponse = $middleware->process($request, new RequestOptions(), $origin);

        self::assertSame(206, $partialResponse->getStatusCode());
        self::assertSame($partialBody, (string) $partialResponse->getBody());
        self::assertSame(200, $fullResponse->getStatusCode());
        self::assertSame($fullBody, (string) $fullResponse->getBody());
        self::assertCount(2, $origin->recorded());
    }

    #[Test]
    public function testCachesAndReusesA204GetResponse(): void
    {
        $factory = new Psr17Factory();
        $middleware = new CacheMiddleware($factory, $factory, new CacheConfig(60), new ArrayCache());
        $request = $factory->createRequest('GET', 'https://api.example.test/document');
        $cacheMarker = Factory::create()->uuid();
        $origin = (new FakeTransport())
            ->push(new Response(204, ['X-Cache-Marker' => $cacheMarker]))
            ->push(new Response(200));

        $firstResponse = $middleware->process($request, new RequestOptions(), $origin);
        $secondResponse = $middleware->process($request, new RequestOptions(), $origin);

        self::assertSame(204, $firstResponse->getStatusCode());
        self::assertSame(204, $secondResponse->getStatusCode());
        self::assertSame($cacheMarker, $secondResponse->getHeaderLine('X-Cache-Marker'));
        self::assertCount(1, $origin->recorded());
    }

    #[Test]
    public function testRangeRequestDoesNotAffectFollowingPlainGetFromOrigin(): void
    {
        $factory = new Psr17Factory();
        $middleware = new CacheMiddleware($factory, $factory, new CacheConfig(60), new ArrayCache());
        $request = $factory->createRequest('GET', 'https://api.example.test/document');
        $fullBody = Factory::create()->text(120);
        $rangeBody = substr($fullBody, 0, 8);
        $origin = (new FakeTransport())
            ->push(new Response(206, [], $rangeBody))
            ->push(new Response(200, [], $fullBody));

        $rangeResponse = $middleware->process(
            $request->withHeader('Range', 'bytes=0-7'),
            new RequestOptions(),
            $origin,
        );
        $plainResponse = $middleware->process($request, new RequestOptions(), $origin);

        self::assertSame(206, $rangeResponse->getStatusCode());
        self::assertSame($rangeBody, (string) $rangeResponse->getBody());
        self::assertSame(200, $plainResponse->getStatusCode());
        self::assertSame($fullBody, (string) $plainResponse->getBody());
        self::assertCount(2, $origin->recorded());
    }

    #[Test]
    public function testKnownOversizedResponseIsNotCachedAndKeepsItsBody(): void
    {
        $factory = new Psr17Factory();
        $cache = new ArrayCache();
        $middleware = new CacheMiddleware($factory, $factory, new CacheConfig(60), $cache);
        $request = $factory->createRequest('GET', 'https://api.example.test/large-document');
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('getSize')->willReturn(104_857_601);
        $stream->method('isSeekable')->willReturn(true);
        $stream->expects(self::never())->method('read');
        $response = new Response(200, [], $stream);
        $origin = (new FakeTransport())->push($response);

        $returned = $middleware->process($request, new RequestOptions(), $origin);

        self::assertSame($response, $returned);
        self::assertSame($stream, $returned->getBody());
        self::assertNull($cache->get($this->cacheKey($request)));
    }

    #[Test]
    public function testUnknownOversizedSeekableResponseIsNotCachedAndRestoresCursor(): void
    {
        $factory = new Psr17Factory();
        $cache = new ArrayCache();
        $middleware = new CacheMiddleware($factory, $factory, new CacheConfig(60), $cache);
        $request = $factory->createRequest('GET', 'https://api.example.test/unknown-document');
        [$stream, $getState] = $this->unknownOversizedSeekableStream(9);
        $response = new Response(200, ['Content-Length' => '1'], $stream);
        $origin = (new FakeTransport())->push($response);

        $returned = $middleware->process($request, new RequestOptions(), $origin);

        /** @var array{position: int, bytesRead: int, maxReadLength: int} $state */
        $state = $getState();
        self::assertSame($response, $returned);
        self::assertSame($stream, $returned->getBody());
        self::assertSame(9, $state['position']);
        self::assertSame(104_857_601, $state['bytesRead']);
        self::assertLessThanOrEqual(8192, $state['maxReadLength']);
        self::assertNull($cache->get($this->cacheKey($request)));
    }

    #[Test]
    public function testUnknownNonSeekableResponseSkipsSnapshotAndRemainsReadable(): void
    {
        $factory = new Psr17Factory();
        $cache = new ArrayCache();
        $middleware = new CacheMiddleware($factory, $factory, new CacheConfig(60), $cache);
        $request = $factory->createRequest('GET', 'https://api.example.test/non-seekable-document');
        $body = Factory::create()->text(120);
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('getSize')->willReturn(null);
        $stream->method('isSeekable')->willReturn(false);
        $stream->expects(self::never())->method('read');
        $stream->expects(self::once())->method('getContents')->willReturn($body);
        $response = new Response(200, [], $stream);
        $origin = (new FakeTransport())->push($response);

        $returned = $middleware->process($request, new RequestOptions(), $origin);

        self::assertSame($response, $returned);
        self::assertSame($stream, $returned->getBody());
        self::assertSame($body, $returned->getBody()->getContents());
        self::assertNull($cache->get($this->cacheKey($request)));
    }

    private function cacheKey(\Psr\Http\Message\RequestInterface $request): string
    {
        return 'http:' . hash('sha256', (string) $request->getUri() . "\n");
    }

    /** @return array{StreamInterface, \Closure(): array{position: int, bytesRead: int, maxReadLength: int}} */
    private function unknownOversizedSeekableStream(int $initialPosition): array
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
