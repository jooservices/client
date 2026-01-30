<?php

declare(strict_types=1);

use GuzzleHttp\Promise\Promise;
use JOOservices\Client\Client\HttpClient;
use JOOservices\Client\Contracts\TransportAdapterInterface;
use JOOservices\Client\ValueObjects\ClientConfig;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

describe('HttpClient Batch', function () {
    it('executes requests concurrently', function () {
        $adapter = Mockery::mock(TransportAdapterInterface::class);
        $client = new HttpClient($adapter, new ClientConfig());

        $req1 = new Request('GET', 'http://a.com');
        $req2 = new Request('GET', 'http://b.com');

        // We mocked using requestAsync logic in HttpClient which calls adapter->sendAsync
        // But in our modified batch() we call requestAsync?
        // Wait, in my modification I called requestAsync with headers from request.

        $adapter->shouldReceive('sendAsync')
            ->times(2)
            ->andReturnUsing(function ($req, $opts) {
                $p = new Promise();
                $p->resolve(new Response(200));
                return $p;
            });

        $results = $client->batch([$req1, $req2]);

        expect($results)->toHaveCount(2);
    });

    it('respects keys in batch results', function () {
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

        expect($results)->toHaveKey('foo');
        expect($results)->toHaveKey('bar');
    });
});
