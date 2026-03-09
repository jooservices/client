<?php

declare(strict_types=1);

namespace Tests\Feature\Resilience;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Exceptions\NetworkConnectionException;
use JOOservices\Client\Resilience\CircuitBreakerConfig;
use JOOservices\Client\Resilience\Storage\InMemoryStateStore;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('feature')]
class CircuitBreakerTest extends TestCase
{
    public function test_circuit_breaker_feature_opens_circuit_after_threshold(): void
    {
        $mock = new MockHandler([
            new RequestException('Error 1', new Request('GET', 'test')),
            new RequestException('Error 2', new Request('GET', 'test')),
            new Response(200),
        ]);
        $handler = HandlerStack::create($mock);
        $store = new InMemoryStateStore();

        $client = ClientBuilder::create()
            ->withOption('handler', $handler)
            ->withCircuitBreaker(new CircuitBreakerConfig(failureThreshold: 2, recoveryTimeoutMs: 1000), $store)
            ->build();

        try {
            $client->get('/1');
        } catch (\Throwable $e) {
        }
        try {
            $client->get('/2');
        } catch (\Throwable $e) {
        }

        $this->assertTrue($store->isCircuitOpen(2, 1000));

        try {
            $client->get('/3');
            $this->fail('Should have thrown Circuit Open Exception');
        } catch (\Throwable $e) {
            $this->assertInstanceOf(NetworkConnectionException::class, $e);
            $this->assertStringContainsString('Circuit Breaker is OPEN', $e->getMessage());
        }
    }
}
