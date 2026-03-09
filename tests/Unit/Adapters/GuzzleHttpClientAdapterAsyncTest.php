<?php

declare(strict_types=1);

namespace Tests\Unit\Adapters;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Psr7\Request;
use JOOservices\Client\Adapters\Guzzle\GuzzleHttpClientAdapter;
use JOOservices\Client\Exceptions\NetworkConnectionException;
use JOOservices\Client\Exceptions\TimeoutException;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('unit')]
class GuzzleHttpClientAdapterAsyncTest extends TestCase
{
    public function test_wraps_generic_connect_exception_in_promise(): void
    {
        $guzzle = Mockery::mock(ClientInterface::class);
        $adapter = new GuzzleHttpClientAdapter($guzzle);

        $request = new Request('GET', '/');

        $rejected = new Promise();
        $rejected->reject(new ConnectException('Connection refused', $request));

        $guzzle->shouldReceive('sendAsync')->andReturn($rejected);

        $resultPromise = $adapter->sendAsync($request);

        $this->expectException(NetworkConnectionException::class);
        $resultPromise->wait();
    }

    public function test_wraps_timeout_exception_in_promise(): void
    {
        $guzzle = Mockery::mock(ClientInterface::class);
        $adapter = new GuzzleHttpClientAdapter($guzzle);

        $request = new Request('GET', '/');
        $rejected = new Promise();
        $rejected->reject(new ConnectException('cURL error 28: Operation timed out', $request));

        $guzzle->shouldReceive('sendAsync')->andReturn($rejected);

        $resultPromise = $adapter->sendAsync($request);

        $this->expectException(TimeoutException::class);
        $resultPromise->wait();
    }
}
