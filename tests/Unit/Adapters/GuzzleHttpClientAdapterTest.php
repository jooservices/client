<?php

declare(strict_types=1);

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use JOOservices\Client\Adapters\Guzzle\GuzzleHttpClientAdapter;
use JOOservices\Client\Exceptions\ClientException;
use JOOservices\Client\Exceptions\NetworkConnectionException;
use JOOservices\Client\Exceptions\TimeoutException;

describe('GuzzleHttpClientAdapter', function () {
    it('sends request and returns response', function () {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], '{"success": true}'),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $guzzle = new GuzzleClient(['handler' => $handlerStack]);

        $adapter = new GuzzleHttpClientAdapter($guzzle);
        $request = new Request('GET', 'https://example.com/api');

        $response = $adapter->send($request);

        expect($response->getStatusCode())->toBe(200);
        expect((string) $response->getBody())->toBe('{"success": true}');
    });

    it('throws TimeoutException on timeout', function () {
        $request = new Request('GET', 'https://example.com/api');
        $mock = new MockHandler([
            new ConnectException('Connection timeout occurred', $request),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $guzzle = new GuzzleClient(['handler' => $handlerStack]);

        $adapter = new GuzzleHttpClientAdapter($guzzle);

        expect(fn () => $adapter->send($request))->toThrow(TimeoutException::class);
    });

    it('throws NetworkConnectionException on connection failure', function () {
        $request = new Request('GET', 'https://example.com/api');
        $mock = new MockHandler([
            new ConnectException('Connection refused', $request),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $guzzle = new GuzzleClient(['handler' => $handlerStack]);

        $adapter = new GuzzleHttpClientAdapter($guzzle);

        expect(fn () => $adapter->send($request))->toThrow(NetworkConnectionException::class);
    });

    it('throws ClientException on other Guzzle errors', function () {
        $request = new Request('GET', 'https://example.com/api');
        $response = new Response(500, [], 'Internal Server Error');
        $mock = new MockHandler([
            new RequestException('Server Error', $request, $response),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $guzzle = new GuzzleClient(['handler' => $handlerStack]);

        $adapter = new GuzzleHttpClientAdapter($guzzle);

        expect(fn () => $adapter->send($request))->toThrow(ClientException::class);
    });

    it('sends async request and returns promise', function () {
        $mock = new MockHandler([
            new Response(200, [], '{"async": true}'),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $guzzle = new GuzzleClient(['handler' => $handlerStack]);

        $adapter = new GuzzleHttpClientAdapter($guzzle);
        $request = new Request('GET', 'https://example.com/api');

        $promise = $adapter->sendAsync($request);
        $response = $promise->wait();

        expect($response->getStatusCode())->toBe(200);
        expect((string) $response->getBody())->toBe('{"async": true}');
    });

    it('passes options to underlying client', function () {
        $mock = new MockHandler([
            new Response(200),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $guzzle = new GuzzleClient(['handler' => $handlerStack]);

        $adapter = new GuzzleHttpClientAdapter($guzzle);
        $request = new Request('GET', 'https://example.com/api');

        // This should not throw - options are passed through
        $response = $adapter->send($request, ['timeout' => 5]);

        expect($response->getStatusCode())->toBe(200);
    });
});
