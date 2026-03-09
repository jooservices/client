<?php

declare(strict_types=1);

namespace JOOservices\Client\Tests\Benchmark;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use JOOservices\Client\Client\ClientBuilder;

/**
 * @Revs(1000)
 * @Iterations(5)
 */
class CoreBench
{
    private $guzzleClient;
    private $jooClient;

    public function __construct()
    {
        // Use static responses so the handler works for long benchmark runs.
        $handler = function ($request, $options) {
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
    public function benchBuilder()
    {
        ClientBuilder::create()
            ->withBaseUri('https://api.example.com')
            ->withTimeout(5)
            ->build();
    }

    /**
     * Baseline: Raw Guzzle Request
     */
    public function benchGuzzleRequest()
    {
        $this->guzzleClient->request('GET', '/test');
    }

    /**
     * Target: JOO Client Request
     */
    public function benchJooRequest()
    {
        $this->jooClient->get('/test');
    }
}
