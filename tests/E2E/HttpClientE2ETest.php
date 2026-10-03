<?php

declare(strict_types=1);

namespace JOOservices\Client\Tests\E2E;

use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Exceptions\TimeoutException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

final class HttpClientE2ETest extends TestCase
{
    private mixed $process;

    /** @var array<int, resource> */
    private array $pipes = [];

    private int $port = 0;

    protected function setUp(): void
    {
        $this->port = $this->startServer();
    }

    protected function tearDown(): void
    {
        $this->stopServer();
    }

    #[Test]
    public function testDecodesGzipWhenCompressionIsEnabledAndTheCallerSetsAcceptEncoding(): void
    {
        $body = 'e2e-gzip-body';
        $client = ClientBuilder::create()
            ->withBaseUri('http://127.0.0.1:' . $this->port . '/')
            ->withCompression()
            ->withTimeout(2)
            ->withConnectTimeout(2)
            ->build();
        $request = $client->requestBuilder()
            ->get('gzip-body.php?' . http_build_query(['value' => $body], '', '&', PHP_QUERY_RFC3986))
            ->withHeader('Accept-Encoding', 'gzip, deflate, br, zstd')
            ->build()
            ->toPsr();
        $response = $client->sendRequest($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame($body, (string) $response->getBody());
        self::assertFalse($response->hasHeader('Content-Encoding'));
    }

    #[Test]
    public function testLeavesGzipCompressedWhenCompressionIsDisabled(): void
    {
        $body = 'e2e-raw-gzip';
        $client = ClientBuilder::create()
            ->withBaseUri('http://127.0.0.1:' . $this->port . '/')
            ->withCompression(false)
            ->withTimeout(2)
            ->withConnectTimeout(2)
            ->build();
        $request = $client->requestBuilder()
            ->get('gzip-body.php?' . http_build_query(['value' => $body], '', '&', PHP_QUERY_RFC3986))
            ->withHeader('Accept-Encoding', 'gzip, deflate, br, zstd')
            ->build()
            ->toPsr();
        $response = $client->sendRequest($request);
        $payload = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertNotSame($body, $payload);
        self::assertSame("\x1f\x8b", substr($payload, 0, 2));
    }

    #[Test]
    public function testFollowsASameHostRedirectAndKeepsTheCookie(): void
    {
        $client = ClientBuilder::create()
            ->withBaseUri('http://127.0.0.1:' . $this->port . '/')
            ->withTimeout(2)
            ->withConnectTimeout(2)
            ->build();
        $request = $client->requestBuilder()
            ->get('redirect-land.php')
            ->withHeader('Cookie', 'sid=keep')
            ->build()
            ->toPsr();
        $response = $client->sendRequest($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('sid=keep', (string) $response->getBody());
    }

    #[Test]
    public function testTimeoutAbortsASlowResponse(): void
    {
        $client = ClientBuilder::create()
            ->withBaseUri('http://127.0.0.1:' . $this->port . '/')
            ->withTimeout(0.4)
            ->withConnectTimeout(0.4)
            ->build();
        $request = $client->requestBuilder()->get('slow.php')->build()->toPsr();
        $started = microtime(true);
        $caught = null;

        try {
            $client->sendRequest($request);
        } catch (Throwable $error) {
            $caught = $error;
        }

        self::assertInstanceOf(TimeoutException::class, $caught);
        self::assertLessThan(1.5, microtime(true) - $started);
    }

    private function startServer(): int
    {
        $port = random_int(20000, 40000);
        $this->process = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', dirname(__DIR__) . '/Fixtures'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $this->pipes,
        );
        self::assertIsResource($this->process);

        try {
            $deadline = microtime(true) + 5.0;
            while (true) {
                $status = proc_get_status($this->process);
                if (! $status['running']) {
                    self::fail('Local HTTP server exited before accepting a connection.');
                }
                if ($this->isServerReachable($port)) {
                    break;
                }
                if (microtime(true) >= $deadline) {
                    self::fail('Local HTTP server did not become ready within 5 seconds.');
                }
                usleep(20_000);
            }
        } catch (Throwable $throwable) {
            $this->stopServer();

            throw $throwable;
        }

        return $port;
    }

    private function isServerReachable(int $port): bool
    {
        set_error_handler(static fn(): bool => true);
        try {
            $socket = stream_socket_client('tcp://127.0.0.1:' . $port, $errorCode, $errorMessage, 0.1);
        } finally {
            restore_error_handler();
        }
        if (is_resource($socket)) {
            fclose($socket);

            return true;
        }

        return false;
    }

    private function stopServer(): void
    {
        if (is_resource($this->process)) {
            proc_terminate($this->process);
        }
        foreach ($this->pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }
        if (is_resource($this->process)) {
            proc_close($this->process);
        }
    }
}
