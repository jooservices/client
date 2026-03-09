<?php

declare(strict_types=1);

namespace Tests\Feature\Resilience;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Resilience\RetryConfig;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('feature')]
class RetryTest extends TestCase
{
    public function test_retry_feature_retries_on_503_and_eventually_succeeds(): void
    {
        $mock = new MockHandler([
            new Response(503),
            new Response(503),
            new Response(200, [], '{"success": true}'),
        ]);
        $handler = HandlerStack::create($mock);

        $client = ClientBuilder::create()
            ->withOption('handler', $handler)
            ->withRetry(new RetryConfig(maxAttempts: 3, baseDelayMs: 1, useJitter: false))
            ->build();

        $response = $client->get('/retry-test');

        $this->assertSame(200, $response->status());
        $this->assertSame(0, $mock->count());
    }
}
