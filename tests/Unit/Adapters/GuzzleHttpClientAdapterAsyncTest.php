<?php

declare(strict_types=1);

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Psr7\Request;
use JOOservices\Client\Adapters\Guzzle\GuzzleHttpClientAdapter;
use JOOservices\Client\Exceptions\NetworkConnectionException;
use JOOservices\Client\Exceptions\TimeoutException;

describe('GuzzleHttpClientAdapter Async', function () {
    it('wraps generic connect exception in promise', function () {
        $guzzle = Mockery::mock(ClientInterface::class);
        $adapter = new GuzzleHttpClientAdapter($guzzle);

        $request = new Request('GET', '/');

        $promise = new Promise();
        $promise->resolve(null); // Just to init
        // We need a rejected promise
        $rejected = new Promise();
        $rejected->reject(new ConnectException('Connection refused', $request));

        $guzzle->shouldReceive('sendAsync')->andReturn($rejected);

        $resultPromise = $adapter->sendAsync($request);

        // Wait on it and expect exception
        expect(fn() => $resultPromise->wait())->toThrow(NetworkConnectionException::class);
    });

    it('wraps timeout exception in promise', function () {
        $guzzle = Mockery::mock(ClientInterface::class);
        $adapter = new GuzzleHttpClientAdapter($guzzle);

        $request = new Request('GET', '/');
        $rejected = new Promise();
        $rejected->reject(new ConnectException('cURL error 28: Operation timed out', $request));

        $guzzle->shouldReceive('sendAsync')->andReturn($rejected);

        $resultPromise = $adapter->sendAsync($request);

        expect(fn() => $resultPromise->wait())->toThrow(TimeoutException::class);
    });
});
