<?php

declare(strict_types=1);

namespace Tests\Unit\Client;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\TransferStats;
use JOOservices\Client\Client\HttpClient;
use JOOservices\Client\Contracts\TransportAdapterInterface;
use JOOservices\Client\Support\TransferStatsBag;
use JOOservices\Client\ValueObjects\ClientConfig;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('unit')]
class HttpClientTest extends TestCase
{
    public function test_it_delegates_request_to_adapter(): void
    {
        $adapter = Mockery::mock(TransportAdapterInterface::class);
        $config = new ClientConfig(baseUri: 'https://test.com');

        $client = new HttpClient($adapter, $config);

        $adapter->shouldReceive('send')
            ->once()
            ->withArgs(function (Request $request, array $options) {
                return $request->getMethod() === 'GET'
                    && (string) $request->getUri() === '/foo'
                    && $options['base_uri'] === 'https://test.com';
            })
            ->andReturn(new Response(200));

        $response = $client->get('/foo');
        $this->assertSame(200, $response->status());
    }

    public function test_it_merges_per_request_options(): void
    {
        $adapter = Mockery::mock(TransportAdapterInterface::class);
        $config = new ClientConfig();

        $client = new HttpClient($adapter, $config);

        $adapter->shouldReceive('send')
            ->once()
            ->withArgs(function ($req, $opts) {
                return $opts['headers']['X-Custom'] === 'Value';
            })
            ->andReturn(new Response(200));

        $client->post('/bar', ['headers' => ['X-Custom' => 'Value']]);
        $this->addToAssertionCount(1);
    }

    public function test_it_captures_transfer_stats_and_preserves_user_on_stats_callback(): void
    {
        $adapter = Mockery::mock(TransportAdapterInterface::class);
        $config = new ClientConfig();
        $client = new HttpClient($adapter, $config);

        $userOnStatsCalled = false;

        $adapter->shouldReceive('send')
            ->once()
            ->withArgs(function ($request, $options) use (&$userOnStatsCalled) {
                $this->assertArrayHasKey(HttpClient::TRANSFER_STATS_OPTION_KEY, $options);
                $this->assertInstanceOf(TransferStatsBag::class, $options[HttpClient::TRANSFER_STATS_OPTION_KEY]);
                $this->assertIsCallable($options['on_stats']);

                $transferStats = new TransferStats(
                    new Request('GET', 'https://example.com/source'),
                    new Response(200),
                    0.01,
                    null,
                    ['primary_ip' => '203.0.113.12', 'local_ip' => '192.168.1.8']
                );

                $options['on_stats']($transferStats);

                $bag = $options[HttpClient::TRANSFER_STATS_OPTION_KEY];
                $this->assertSame('203.0.113.12', $bag->targetIp);
                $this->assertSame('192.168.1.8', $bag->localIp);
                $this->assertTrue($userOnStatsCalled);

                return true;
            })
            ->andReturn(new Response(200));

        $client->get('https://example.com/source', [
            'on_stats' => function (TransferStats $stats) use (&$userOnStatsCalled): void {
                $userOnStatsCalled = true;
            },
        ]);
    }
}
