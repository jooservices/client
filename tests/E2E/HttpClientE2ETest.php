<?php

declare(strict_types=1);

namespace JOOservices\Client\Tests\E2E;

use Faker\Factory;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\Handler\StreamHandler;
use GuzzleHttp\HandlerStack;
use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Contracts\TransportCapabilities;
use JOOservices\Client\Contracts\TransportInterface;
use JOOservices\Client\Dto\RequestOptions;
use JOOservices\Client\Exceptions\NetworkConnectionException;
use JOOservices\Client\Exceptions\TimeoutException;
use JOOservices\Client\Transport\GuzzleTransport;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
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
    public function testSupportedTransportsReachTheSameLocalTargetServer(): void
    {
        $baseUri = 'http://127.0.0.1:' . $this->port . '/';
        $factory = new Psr17Factory();
        $guzzleCurl = new GuzzleClient([
            'handler' => HandlerStack::create(new CurlHandler()),
            'proxy' => ['http' => '', 'https' => ''],
        ]);
        $guzzleStream = new GuzzleClient([
            'handler' => HandlerStack::create(new StreamHandler()),
            'proxy' => ['http' => '', 'https' => ''],
        ]);
        $transports = [
            'default ClientBuilder/CurlTransport' => ClientBuilder::create()
                ->withBaseUri($baseUri)
                ->build(),
            'GuzzleTransport/CurlHandler' => ClientBuilder::create()
                ->withBaseUri($baseUri)
                ->withTransport(new GuzzleTransport($guzzleCurl, $factory, $factory))
                ->build(),
            'GuzzleTransport/StreamHandler' => ClientBuilder::create()
                ->withBaseUri($baseUri)
                ->withTransport(new GuzzleTransport($guzzleStream, $factory, $factory))
                ->build(),
            'injected PSR-18 client/PsrTransport' => ClientBuilder::create()
                ->withBaseUri($baseUri)
                ->withPsr18(new GuzzleClient([
                    'handler' => HandlerStack::create(new CurlHandler()),
                    'proxy' => ['http' => '', 'https' => ''],
                ]))
                ->build(),
        ];

        foreach ($transports as $name => $client) {
            $payload = Factory::create()->bothify('matrix-????-####');
            $request = $client->requestBuilder()
                ->post('echo-body.php')
                ->withBody($payload)
                ->build()
                ->toPsr();
            $response = $client->sendRequest($request);

            self::assertSame(200, $response->getStatusCode(), $name);
            self::assertSame($payload, (string) $response->getBody(), $name . ' must reach the shared server and receive its echoed payload');
        }

        $firstTransport = new class implements TransportInterface {
            public int $attempts = 0;

            public function capabilities(): TransportCapabilities
            {
                return new TransportCapabilities([
                    'timeout' => true,
                    'connectTimeout' => true,
                    'compression' => true,
                    'proxy' => true,
                    'verifySsl' => true,
                    'allowRedirects' => true,
                ]);
            }

            public function handle(RequestInterface $request, RequestOptions $options): ResponseInterface
            {
                unset($options);
                ++$this->attempts;

                throw new NetworkConnectionException($request, 'Deterministic first transport failure.');
            }
        };
        $fallbackClient = ClientBuilder::create()
            ->withBaseUri($baseUri)
            ->withFailoverTransport([
                $firstTransport,
                new GuzzleTransport($guzzleCurl, $factory, $factory),
            ])
            ->build();
        $fallbackPayload = Factory::create()->bothify('fallback-????-####');
        $fallbackRequest = $fallbackClient->requestBuilder()
            ->post('echo-body.php')
            ->withBody($fallbackPayload)
            ->build()
            ->toPsr();
        $fallbackResponse = $fallbackClient->sendRequest($fallbackRequest);

        self::assertSame(1, $firstTransport->attempts, 'The deterministic primary must fail once before failover.');
        self::assertSame(200, $fallbackResponse->getStatusCode());
        self::assertSame($fallbackPayload, (string) $fallbackResponse->getBody(), 'The fallback transport must reach the same local target server.');
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
        $this->process = null;

        for ($attempt = 1; $attempt <= 3; ++$attempt) {
            $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
            self::assertIsResource($socket, sprintf('Could not reserve a local port: %s (%d).', $errorMessage, $errorCode));
            $address = stream_socket_get_name($socket, false);
            fclose($socket);

            self::assertIsString($address);
            $separator = strrpos($address, ':');
            self::assertNotFalse($separator);
            $port = (int) substr($address, $separator + 1);
            self::assertGreaterThan(0, $port);

            $this->pipes = [];
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
                        break;
                    }
                    if ($this->isServerReachable($port)) {
                        return $port;
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

            $this->stopServer();
        }

        self::fail('Local HTTP server exited before accepting a connection after 3 attempts.');
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
        $this->process = null;
        $this->pipes = [];
    }
}
