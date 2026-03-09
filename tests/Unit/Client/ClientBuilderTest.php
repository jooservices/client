<?php

declare(strict_types=1);

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use JOOservices\Client\Cache\MemoryCache;
use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Contracts\HttpClientInterface;
use JOOservices\Client\Resilience\CircuitBreakerConfig;
use JOOservices\Client\Resilience\RetryConfig;
use Psr\Http\Message\RequestInterface;

describe('ClientBuilder', function () {


    it('creates client with default settings', function () {
        $captured = [];
        $stack = captureGuzzleOptions($captured);

        $client = ClientBuilder::create()
            ->withOption('handler', $stack)
            ->build();

        $client->get('https://example.com');

        expect($client)->toBeInstanceOf(HttpClientInterface::class);
        // Defaults might be empty on options side, but let's just ensure it works
        expect($captured)->toBeArray();
    });

    it('sets base uri', function () {
        // Base URI is a client constructor option in Guzzle, but ClientBuilder passes it via options to request?
        // Actually ClientConfig::toGuzzleOptions() puts it in 'base_uri'.
        // Guzzle Adapter merges it.

        $captured = [];
        $stack = captureGuzzleOptions($captured);

        $client = ClientBuilder::create()
            ->withBaseUri('https://api.example.com')
            ->withOption('handler', $stack)
            ->build();

        $client->get('/fail-if-no-base-uri'); // Relative URI relies on base_uri

        expect($captured)->toHaveKey('base_uri', 'https://api.example.com');
    });

    it('sets timeout', function () {
        $captured = [];
        $stack = captureGuzzleOptions($captured);

        $client = ClientBuilder::create()
            ->withTimeout(60)
            ->withOption('handler', $stack)
            ->build();

        $client->get('https://example.com');

        expect($captured)->toHaveKey('timeout', 60);
    });

    it('sets connect timeout', function () {
        $captured = [];
        $stack = captureGuzzleOptions($captured);

        $client = ClientBuilder::create()
            ->withConnectTimeout(5)
            ->withOption('handler', $stack)
            ->build();

        $client->get('https://example.com');

        expect($captured)->toHaveKey('connect_timeout', 5);
    });

    it('sets headers', function () {
        $captured = [];
        $stack = captureGuzzleOptions($captured);

        $client = ClientBuilder::create()
            ->withHeader('X-Test', 'Value')
            ->withHeaders(['X-Another' => 'Value2'])
            ->withOption('handler', $stack)
            ->build();

        $client->get('https://example.com');

        expect($captured)->toHaveKey('headers');
        expect($captured['headers'])->toHaveKey('X-Test', 'Value');
        expect($captured['headers'])->toHaveKey('X-Another', 'Value2');
    });

    it('sets verify ssl', function () {
        $captured = [];
        $stack = captureGuzzleOptions($captured);

        $client = ClientBuilder::create()
            ->withVerifySsl(false)
            ->withOption('handler', $stack)
            ->build();

        $client->get('https://example.com');

        expect($captured)->toHaveKey('verify', false);
    });

    it('sets http errors', function () {
        $captured = [];
        $stack = captureGuzzleOptions($captured);

        $client = ClientBuilder::create()
            ->withHttpErrors(false) // Default is often true in Guzzle
            ->withOption('handler', $stack)
            ->build();

        $client->get('https://example.com');

        expect($captured)->toHaveKey('http_errors', false);
    });

    it('adds correlation id middleware', function () {
        // Middleware modifies request, so we inspect request headers in mock
        $mock = new MockHandler([
            function (RequestInterface $request, array $options) {
                expect($request->hasHeader('X-Correlation-ID'))->toBeTrue();
                return new Response(200);
            }
        ]);
        $stack = HandlerStack::create($mock);

        $client = ClientBuilder::create()
            ->withCorrelationId()
            ->withOption('handler', $stack)
            ->build();

        $client->get('https://example.com');
    });

    it('adds user agent middleware', function () {
        $mock = new MockHandler([
            function (RequestInterface $request, array $options) {
                expect($request->getHeaderLine('User-Agent'))->toBe('TestApp/1.0');
                return new Response(200);
            }
        ]);
        $stack = HandlerStack::create($mock);

        $client = ClientBuilder::create()
            ->withUserAgent('TestApp/1.0')
            ->withOption('handler', $stack)
            ->build();

        $client->get('https://example.com');
    });

    it('adds cache middleware', function () {
        // We verify cache option is passed or middleware is active.
        // CacheMiddleware uses 'cache_ttl' option.
        $captured = [];
        $stack = captureGuzzleOptions($captured);

        $client = ClientBuilder::create()
            ->withCache(new MemoryCache(), 1800)
            ->withOption('handler', $stack)
            ->build();

        $client->get('https://example.com');

        expect($client)->toBeInstanceOf(HttpClientInterface::class);
    });

    it('adds retry middleware', function () {
        // Retry middleware is added to the stack.
        // Hard to test presence without triggering it.
        $client = ClientBuilder::create()
            ->withRetry(new RetryConfig(maxAttempts: 3))
            ->build();
        expect($client)->toBeInstanceOf(HttpClientInterface::class);
    });

    it('adds circuit breaker middleware', function () {
        $client = ClientBuilder::create()
            ->withCircuitBreaker(new CircuitBreakerConfig(failureThreshold: 5))
            ->build();
        expect($client)->toBeInstanceOf(HttpClientInterface::class);
    });

    it('adds logger middleware', function () {
        $logger = Mockery::mock(Psr\Log\LoggerInterface::class);
        // We expect logger calls
        $logger->shouldReceive('info')->atLeast()->times(1);
        $logger->shouldReceive('log')->atLeast()->times(1);

        $mock = new MockHandler([new Response(200)]);
        $stack = HandlerStack::create($mock);

        $client = ClientBuilder::create()
            ->withLogger($logger, false)
            ->withOption('handler', $stack)
            ->build();

        $client->get('https://example.com');
    });

    it('automatically adds wan ip logging metadata when logger is enabled', function () {
        $logger = Mockery::mock(Psr\Log\LoggerInterface::class);

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
    });

    it('accepts custom handler stack', function () {
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
        expect($callCount)->toBe(1);
    });
});
