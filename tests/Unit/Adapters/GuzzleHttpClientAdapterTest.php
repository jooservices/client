<?php

declare(strict_types=1);

namespace Tests\Unit\Adapters;

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
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('unit')]
class GuzzleHttpClientAdapterTest extends TestCase
{
    public function test_sends_request_and_returns_response(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], '{"success": true}'),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $guzzle = new GuzzleClient(['handler' => $handlerStack]);

        $adapter = new GuzzleHttpClientAdapter($guzzle);
        $request = new Request('GET', 'https://example.com/api');

        $response = $adapter->send($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{"success": true}', (string) $response->getBody());
    }

    public function test_throws_TimeoutException_on_timeout(): void
    {
        $request = new Request('GET', 'https://example.com/api');
        $mock = new MockHandler([
            new ConnectException('Connection timeout occurred', $request),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $guzzle = new GuzzleClient(['handler' => $handlerStack]);

        $adapter = new GuzzleHttpClientAdapter($guzzle);

        $this->expectException(TimeoutException::class);
        $adapter->send($request);
    }

    public function test_throws_NetworkConnectionException_on_connection_failure(): void
    {
        $request = new Request('GET', 'https://example.com/api');
        $mock = new MockHandler([
            new ConnectException('Connection refused', $request),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $guzzle = new GuzzleClient(['handler' => $handlerStack]);

        $adapter = new GuzzleHttpClientAdapter($guzzle);

        $this->expectException(NetworkConnectionException::class);
        $adapter->send($request);
    }

    public function test_throws_ClientException_on_other_Guzzle_errors(): void
    {
        $request = new Request('GET', 'https://example.com/api');
        $response = new Response(500, [], 'Internal Server Error');
        $mock = new MockHandler([
            new RequestException('Server Error', $request, $response),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $guzzle = new GuzzleClient(['handler' => $handlerStack]);

        $adapter = new GuzzleHttpClientAdapter($guzzle);

        $this->expectException(ClientException::class);
        $adapter->send($request);
    }

    public function test_sends_async_request_and_returns_promise(): void
    {
        $mock = new MockHandler([
            new Response(200, [], '{"async": true}'),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $guzzle = new GuzzleClient(['handler' => $handlerStack]);

        $adapter = new GuzzleHttpClientAdapter($guzzle);
        $request = new Request('GET', 'https://example.com/api');

        $promise = $adapter->sendAsync($request);
        $response = $promise->wait();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{"async": true}', (string) $response->getBody());
    }

    public function test_passes_options_to_underlying_client(): void
    {
        $mock = new MockHandler([
            new Response(200),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $guzzle = new GuzzleClient(['handler' => $handlerStack]);

        $adapter = new GuzzleHttpClientAdapter($guzzle);
        $request = new Request('GET', 'https://example.com/api');

        $response = $adapter->send($request, ['timeout' => 5]);

        $this->assertSame(200, $response->getStatusCode());
    }
}
