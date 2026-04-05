<?php

declare(strict_types=1);

namespace JOOservices\Client\Tests\Benchmark;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Contracts\HttpClientInterface;

/**
 * @Revs(1000)
 * @Iterations(5)
 */
class CoreBench
{
    private GuzzleClient $guzzleClient;

    private HttpClientInterface $jooClient;

    public function __construct()
    {
        // Use static responses so the handler works for long benchmark runs.
        $handler = static function (mixed $request, mixed $options): PromiseInterface {
            unset($request, $options);

            return \GuzzleHttp\Promise\Create::promiseFor(
                new Response(200, [], '{"status":"ok"}')
            );
        };
        $stack = HandlerStack::create($handler);

        // Raw Guzzle
        $this->guzzleClient = new GuzzleClient(['handler' => $stack]);

        // JOO Client
        $this->jooClient = ClientBuilder::create()
            ->withOption('handler', $stack) // Use same handler
            ->build();
    }

    /**
     * Measure overhead of Builder
     */
    public function benchBuilder(): void
    {
        ClientBuilder::create()
            ->withBaseUri('https://api.example.com')
            ->withTimeout(5)
            ->build();
    }

    /**
     * Baseline: Raw Guzzle Request
     */
    public function benchGuzzleRequest(): void
    {
        $this->guzzleClient->request('GET', '/test');
    }

    /**
     * Target: JOO Client Request
     */
    public function benchJooRequest(): void
    {
        $this->jooClient->get('/test');
    }
}
