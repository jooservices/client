<?php

declare(strict_types=1);

namespace JOOservices\Client\Tests\Integration;

use Faker\Factory;
use GuzzleHttp\Client;
use JOOservices\Client\Dto\RequestOptions;
use JOOservices\Client\Transport\GuzzleTransport;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

final class GuzzleTransportTest extends TestCase
{
    private mixed $process;

    /** @var array<int, resource> */
    private array $pipes = [];

    #[Test]
    public function testDecodesGzipResponseWhenCompressionIsEnabled(): void
    {
        $port = $this->startServer();

        try {
            $factory = new Psr17Factory();
            $body = Factory::create()->sentence(8);
            $request = $factory->createRequest(
                'GET',
                'http://127.0.0.1:' . $port . '/gzip-body.php?' . http_build_query(
                    ['value' => $body],
                    '',
                    '&',
                    PHP_QUERY_RFC3986,
                ),
            );
            $transport = new GuzzleTransport(new Client(['proxy' => ['http' => '', 'https' => '']]), $factory, $factory);
            $response = $transport->handle(
                $request,
                new RequestOptions(timeout: 2, connectTimeout: 2, compression: true),
            );

            self::assertSame($body, (string) $response->getBody());
            self::assertFalse($response->hasHeader('Content-Encoding'));
            self::assertFalse($response->hasHeader('Content-Length'));
            self::assertFalse($response->hasHeader('Transfer-Encoding'));
            self::assertSame('Accept-Encoding', $response->getHeaderLine('Vary'));
        } finally {
            $this->stopServer();
        }
    }

    private function startServer(): int
    {
        $port = random_int(20000, 40000);
        $this->process = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', dirname(__DIR__) . '/Fixtures'], [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $this->pipes);
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
