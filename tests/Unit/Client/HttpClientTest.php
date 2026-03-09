<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\TransferStats;
use JOOservices\Client\Client\HttpClient;
use JOOservices\Client\Contracts\TransportAdapterInterface;
use JOOservices\Client\Support\TransferStatsBag;
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

test('it captures transfer stats and preserves user on_stats callback', function () {
    $adapter = Mockery::mock(TransportAdapterInterface::class);
    $config = new ClientConfig();
    $client = new HttpClient($adapter, $config);

    $userOnStatsCalled = false;

    $adapter->shouldReceive('send')
        ->once()
        ->withArgs(function ($request, $options) use (&$userOnStatsCalled) {
            expect($options)->toHaveKey(HttpClient::TRANSFER_STATS_OPTION_KEY);
            expect($options[HttpClient::TRANSFER_STATS_OPTION_KEY])->toBeInstanceOf(TransferStatsBag::class);
            expect($options['on_stats'])->toBeCallable();

            $transferStats = new TransferStats(
                new Request('GET', 'https://example.com/source'),
                new Response(200),
                0.01,
                null,
                ['primary_ip' => '203.0.113.12', 'local_ip' => '192.168.1.8']
            );

            $options['on_stats']($transferStats);

            $bag = $options[HttpClient::TRANSFER_STATS_OPTION_KEY];
            expect($bag->targetIp)->toBe('203.0.113.12');
            expect($bag->localIp)->toBe('192.168.1.8');
            expect($userOnStatsCalled)->toBeTrue();

            return true;
        })
        ->andReturn(new Response(200));

    $client->get('https://example.com/source', [
        'on_stats' => function (TransferStats $stats) use (&$userOnStatsCalled): void {
            $userOnStatsCalled = true;
        },
    ]);
});
