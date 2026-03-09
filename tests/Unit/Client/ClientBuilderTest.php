<?php

declare(strict_types=1);

namespace Tests\Unit\Client;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use JOOservices\Client\Cache\MemoryCache;
use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Contracts\HttpClientInterface;
use JOOservices\Client\Resilience\CircuitBreakerConfig;
use JOOservices\Client\Resilience\RetryConfig;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use Psr\Http\Message\RequestInterface;
use Tests\TestCase;

#[Group('unit')]
class ClientBuilderTest extends TestCase
{
    public function test_creates_client_with_default_settings(): void
    {
        $captured = [];
        $stack = $this->captureGuzzleOptions($captured);

        $client = ClientBuilder::create()
            ->withOption('handler', $stack)
            ->build();

        $client->get('https://example.com');

        $this->assertInstanceOf(HttpClientInterface::class, $client);
        $this->assertIsArray($captured);
    }

    public function test_sets_base_uri(): void
    {
        $captured = [];
        $stack = $this->captureGuzzleOptions($captured);

        $client = ClientBuilder::create()
            ->withBaseUri('https://api.example.com')
            ->withOption('handler', $stack)
            ->build();

        $client->get('/fail-if-no-base-uri');

        $this->assertArrayHasKey('base_uri', $captured);
        $this->assertSame('https://api.example.com', $captured['base_uri']);
    }

    public function test_sets_timeout(): void
    {
        $captured = [];
        $stack = $this->captureGuzzleOptions($captured);

        $client = ClientBuilder::create()
            ->withTimeout(60)
            ->withOption('handler', $stack)
            ->build();

        $client->get('https://example.com');

        $this->assertArrayHasKey('timeout', $captured);
        $this->assertSame(60, $captured['timeout']);
    }

    public function test_sets_connect_timeout(): void
    {
        $captured = [];
        $stack = $this->captureGuzzleOptions($captured);

        $client = ClientBuilder::create()
            ->withConnectTimeout(5)
            ->withOption('handler', $stack)
            ->build();

        $client->get('https://example.com');

        $this->assertArrayHasKey('connect_timeout', $captured);
        $this->assertSame(5, $captured['connect_timeout']);
    }

    public function test_sets_headers(): void
    {
        $captured = [];
        $stack = $this->captureGuzzleOptions($captured);

        $client = ClientBuilder::create()
            ->withHeader('X-Test', 'Value')
            ->withHeaders(['X-Another' => 'Value2'])
            ->withOption('handler', $stack)
            ->build();

        $client->get('https://example.com');

        $this->assertArrayHasKey('headers', $captured);
        $this->assertArrayHasKey('X-Test', $captured['headers']);
        $this->assertSame('Value', $captured['headers']['X-Test']);
        $this->assertArrayHasKey('X-Another', $captured['headers']);
        $this->assertSame('Value2', $captured['headers']['X-Another']);
    }

    public function test_sets_verify_ssl(): void
    {
        $captured = [];
        $stack = $this->captureGuzzleOptions($captured);

        $client = ClientBuilder::create()
            ->withVerifySsl(false)
            ->withOption('handler', $stack)
            ->build();

        $client->get('https://example.com');

        $this->assertArrayHasKey('verify', $captured);
        $this->assertFalse($captured['verify']);
    }

    public function test_sets_http_errors(): void
    {
        $captured = [];
        $stack = $this->captureGuzzleOptions($captured);

        $client = ClientBuilder::create()
            ->withHttpErrors(false)
            ->withOption('handler', $stack)
            ->build();

        $client->get('https://example.com');

        $this->assertArrayHasKey('http_errors', $captured);
        $this->assertFalse($captured['http_errors']);
    }

    public function test_adds_correlation_id_middleware(): void
    {
        $mock = new MockHandler([
            function (RequestInterface $request, array $options) {
                $this->assertTrue($request->hasHeader('X-Correlation-ID'));
                return new Response(200);
            }
        ]);
        $stack = HandlerStack::create($mock);

        $client = ClientBuilder::create()
            ->withCorrelationId()
            ->withOption('handler', $stack)
            ->build();

        $client->get('https://example.com');
    }

    public function test_adds_user_agent_middleware(): void
    {
        $mock = new MockHandler([
            function (RequestInterface $request, array $options) {
                $this->assertSame('TestApp/1.0', $request->getHeaderLine('User-Agent'));
                return new Response(200);
            }
        ]);
        $stack = HandlerStack::create($mock);

        $client = ClientBuilder::create()
            ->withUserAgent('TestApp/1.0')
            ->withOption('handler', $stack)
            ->build();

        $client->get('https://example.com');
    }

    public function test_adds_cache_middleware(): void
    {
        $captured = [];
        $stack = $this->captureGuzzleOptions($captured);

        $client = ClientBuilder::create()
            ->withCache(new MemoryCache(), 1800)
            ->withOption('handler', $stack)
            ->build();

        $client->get('https://example.com');

        $this->assertInstanceOf(HttpClientInterface::class, $client);
    }

    public function test_adds_retry_middleware(): void
    {
        $client = ClientBuilder::create()
            ->withRetry(new RetryConfig(maxAttempts: 3))
            ->build();
        $this->assertInstanceOf(HttpClientInterface::class, $client);
    }

    public function test_adds_circuit_breaker_middleware(): void
    {
        $client = ClientBuilder::create()
            ->withCircuitBreaker(new CircuitBreakerConfig(failureThreshold: 5))
            ->build();
        $this->assertInstanceOf(HttpClientInterface::class, $client);
    }

    public function test_adds_logger_middleware(): void
    {
        $logger = Mockery::mock(\Psr\Log\LoggerInterface::class);
        $logger->shouldReceive('info')->atLeast()->times(1);
        $logger->shouldReceive('log')->atLeast()->times(1);

        $mock = new MockHandler([new Response(200)]);
        $stack = HandlerStack::create($mock);

        $client = ClientBuilder::create()
            ->withLogger($logger, false)
            ->withOption('handler', $stack)
            ->build();

        $client->get('https://example.com');
        $this->addToAssertionCount(1);
    }

    public function test_automatically_adds_wan_ip_logging_metadata_when_logger_is_enabled(): void
    {
        $logger = Mockery::mock(\Psr\Log\LoggerInterface::class);

        $logger->shouldReceive('info')
            ->atLeast()
            ->times(1)
            ->withArgs(function ($message, $context) {
                return array_key_exists('wan_ip', $context);
            });
        $logger->shouldReceive('log')->atLeast()->times(1);

        $mock = new MockHandler([new Response(200)]);
        $stack = HandlerStack::create($mock);

        $client = ClientBuilder::create()
            ->withLogger($logger, false)
            ->withOption('handler', $stack)
            ->build();

        $client->get('https://example.com');
        $this->addToAssertionCount(1);
    }

    public function test_accepts_custom_handler_stack(): void
    {
        $callCount = 0;
        $handler = function ($request, $options) use (&$callCount) {
            $callCount++;
            return \GuzzleHttp\Promise\Create::promiseFor(new Response(200));
        };
        $stack = HandlerStack::create($handler);

        $client = ClientBuilder::create()
            ->withOption('handler', $stack)
            ->build();

        $client->get('https://example.com');
        $this->assertSame(1, $callCount);
    }
}
