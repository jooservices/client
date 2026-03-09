<?php

declare(strict_types=1);

namespace Tests\Unit\Client;

use Exception;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use JOOservices\Client\Client\HttpClient;
use JOOservices\Client\Contracts\TransportAdapterInterface;
use JOOservices\Client\ValueObjects\ClientConfig;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('unit')]
class HttpClientBatchTest extends TestCase
{
    public function test_executes_requests_concurrently(): void
    {
        $adapter = Mockery::mock(TransportAdapterInterface::class);
        $client = new HttpClient($adapter, new ClientConfig());

        $req1 = new Request('GET', 'http://a.com');
        $req2 = new Request('GET', 'http://b.com');

        $adapter->shouldReceive('sendAsync')
            ->times(2)
            ->andReturnUsing(function ($req, $opts) {
                $p = new Promise();
                $p->resolve(new Response(200));
                return $p;
            });

        $results = $client->batch([$req1, $req2]);

        $this->assertCount(2, $results);
    }

    public function test_respects_keys_in_batch_results(): void
    {
        $adapter = Mockery::mock(TransportAdapterInterface::class);
        $client = new HttpClient($adapter, new ClientConfig());

        $adapter->shouldReceive('sendAsync')->andReturnUsing(function () {
            $p = new Promise();
            $p->resolve(new Response(200));
            return $p;
        });

        $requests = [
            'foo' => new Request('GET', 'http://foo.com'),
            'bar' => new Request('GET', 'http://bar.com'),
        ];

        $results = $client->batch($requests);

        if (!array_key_exists('foo', $results)) {
            throw new Exception('Results keys: ' . implode(', ', array_keys($results)));
        }

        $this->assertArrayHasKey('foo', $results);
        $this->assertArrayHasKey('bar', $results);
    }
}
