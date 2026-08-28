<?php

declare(strict_types=1);

namespace JOOservices\Client\Tests\Integration;

use JOOservices\Client\Dto\RequestOptions;
use JOOservices\Client\Exceptions\DownloadSizeExceededException;
use JOOservices\Client\Request\RequestBuilder;
use JOOservices\Client\Transport\Curl\CurlExchange;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CurlExchangeTest extends TestCase
{
    #[Test]
    public function testStreamsAResponseFromALocalHttpServer(): void
    {
        $port = random_int(20000, 40000);
        $command = [PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', dirname(__DIR__) . '/Fixtures'];
        $process = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);
        self::assertIsResource($process);
        usleep(100_000);

        try {
            $factory = new Psr17Factory();
            $request = $factory->createRequest('POST', 'http://127.0.0.1:' . $port . '/missing')
                ->withHeader('X-Test', 'one')
                ->withBody($factory->createStream('body'));
            $response = (new CurlExchange($factory, $factory))->send($request, new RequestOptions(timeout: 2, connectTimeout: 2));

            self::assertSame(404, $response->getStatusCode());
            self::assertTrue($response->getBody()->isSeekable());
        } finally {
            proc_terminate($process);
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
            proc_close($process);
        }
    }

    #[Test]
    public function testSendsThePostBodyIntactOverTheWire(): void
    {
        $port = random_int(20000, 40000);
        $command = [PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', dirname(__DIR__) . '/Fixtures'];
        $process = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);
        self::assertIsResource($process);
        usleep(100_000);

        try {
            $factory = new Psr17Factory();
            $payload = str_repeat('payload-chunk-', 500);
            $request = $factory->createRequest('POST', 'http://127.0.0.1:' . $port . '/echo-body.php')
                ->withBody($factory->createStream($payload));
            $response = (new CurlExchange($factory, $factory))->send($request, new RequestOptions(timeout: 2, connectTimeout: 2));

            self::assertSame(200, $response->getStatusCode());
            self::assertSame((string) strlen($payload), $response->getHeaderLine('X-Received-Length'));
            self::assertSame($payload, (string) $response->getBody());
        } finally {
            proc_terminate($process);
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
            proc_close($process);
        }
    }

    #[Test]
    public function testSendsAMultipartBodyIntactOverTheWire(): void
    {
        $port = random_int(20000, 40000);
        $command = [PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', dirname(__DIR__) . '/Fixtures'];
        $process = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);
        self::assertIsResource($process);
        usleep(100_000);

        try {
            $factory = new Psr17Factory();
            $handle = fopen('php://temp', 'r+b');
            self::assertIsResource($handle);
            fwrite($handle, "hello\0world");
            rewind($handle);
            $request = RequestBuilder::create($factory, $factory, $factory)
                ->post('http://127.0.0.1:' . $port . '/echo-multipart.php')
                ->withMultipart([
                    ['name' => 'title', 'contents' => 'Photo'],
                    ['name' => 'file', 'contents' => $handle, 'filename' => 'a.bin', 'contentType' => 'application/octet-stream'],
                ])
                ->toPsr();
            $response = (new CurlExchange($factory, $factory))->send($request, new RequestOptions(timeout: 2, connectTimeout: 2));
            $payload = json_decode((string) $response->getBody(), true);

            self::assertSame(200, $response->getStatusCode());
            self::assertIsArray($payload);
            $post = $payload['post'] ?? null;
            $files = $payload['files'] ?? null;
            self::assertIsArray($post);
            self::assertIsArray($files);
            $file = $files['file'] ?? null;
            self::assertIsArray($file);
            self::assertSame('Photo', $post['title'] ?? null);
            self::assertSame('a.bin', $file['name'] ?? null);
            self::assertSame("hello\0world", $file['contents'] ?? null);
            fclose($handle);
        } finally {
            proc_terminate($process);
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
            proc_close($process);
        }
    }

    #[Test]
    public function testAbortsADownloadThatExceedsTheConfiguredSizeLimit(): void
    {
        $port = random_int(20000, 40000);
        $command = [PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', dirname(__DIR__) . '/Fixtures'];
        $process = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);
        self::assertIsResource($process);
        usleep(100_000);

        try {
            $factory = new Psr17Factory();
            $request = $factory->createRequest('GET', 'http://127.0.0.1:' . $port . '/big-body.php');
            $exchange = new CurlExchange($factory, $factory, maxResponseBytes: 1024);

            $this->expectException(DownloadSizeExceededException::class);
            $exchange->send($request, new RequestOptions(timeout: 5, connectTimeout: 2));
        } finally {
            proc_terminate($process);
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
            proc_close($process);
        }
    }

    #[Test]
    public function testPinnedAddressesForceTheConnectionInsteadOfARealDnsLookup(): void
    {
        $port = random_int(20000, 40000);
        $command = [PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', dirname(__DIR__) . '/Fixtures'];
        $process = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);
        self::assertIsResource($process);
        usleep(100_000);

        try {
            $factory = new Psr17Factory();
            // This hostname does not resolve via real DNS at all — the request only succeeds if
            // CURLOPT_RESOLVE actually pins it to 127.0.0.1 instead of curl doing its own lookup. This
            // is the concrete mechanism that closes the DNS-rebinding TOCTOU window: whatever
            // RedirectTargetPolicy verified as public is exactly what curl connects to, not whatever a
            // second, independent DNS query might answer.
            $request = $factory->createRequest('GET', 'http://definitely-fake-host-for-testing.invalid:' . $port . '/echo-body.php')
                ->withBody($factory->createStream('pinned'));

            $response = (new CurlExchange($factory, $factory))->send(
                $request,
                new RequestOptions(timeout: 2, connectTimeout: 2),
                ['127.0.0.1'],
            );

            self::assertSame(200, $response->getStatusCode());
            self::assertSame('pinned', (string) $response->getBody());
        } finally {
            proc_terminate($process);
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
            proc_close($process);
        }
    }
}
