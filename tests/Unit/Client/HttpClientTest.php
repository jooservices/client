<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use JOOservices\Client\Client\HttpClient;
use JOOservices\Client\Contracts\TransportAdapterInterface;
use JOOservices\Client\ValueObjects\ClientConfig;

test('it delegates request to adapter', function () {
    $adapter = Mockery::mock(TransportAdapterInterface::class);
    $config = new ClientConfig(baseUri: 'https://test.com');

    $client = new HttpClient($adapter, $config);

    $adapter->shouldReceive('send')
        ->once()
        ->withArgs(function (Request $request, array $options) {
            return $request->getMethod() === 'GET'
                && (string) $request->getUri() === '/foo'
                && $options['base_uri'] === 'https://test.com'; // Merged from global
        })
        ->andReturn(new Response(200));

    $response = $client->get('/foo');
    expect($response->status())->toBe(200);
});

test('it merges per-request options', function () {
    $adapter = Mockery::mock(TransportAdapterInterface::class);
    $config = new ClientConfig(); // defaults

    $client = new HttpClient($adapter, $config);

    $adapter->shouldReceive('send')
        ->once()
        ->withArgs(function ($req, $opts) {
            return $opts['headers']['X-Custom'] === 'Value';
        })
        ->andReturn(new Response(200));

    $client->post('/bar', ['headers' => ['X-Custom' => 'Value']]);
});
