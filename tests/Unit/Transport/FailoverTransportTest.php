<?php

declare(strict_types=1);

namespace JOOservices\Client\Tests\Unit\Transport;

use JOOservices\Client\Dto\RequestOptions;
use JOOservices\Client\Exceptions\NetworkConnectionException;
use JOOservices\Client\Exceptions\TimeoutException;
use JOOservices\Client\Testing\FakeTransport;
use JOOservices\Client\Transport\FailoverTransport;
use JOOservices\Client\Transport\PsrTransport;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response as PsrResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;

final class FailoverTransportTest extends TestCase
{
    #[Test]
    public function testFailsOverOnNetworkErrorButNotHttpStatus(): void
    {
        $factory = new Psr17Factory();
        $request = $factory->createRequest('GET', 'https://abc.com');
        $first = (new FakeTransport())->push(new NetworkConnectionException($request, 'down'));
        $second = (new FakeTransport())->push(new PsrResponse(200, [], 'ok'));
        $failover = new FailoverTransport([$first, $second]);

        $response = $failover->handle($request, new RequestOptions());

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function testDoesNotFailOverOnTimeout(): void
    {
        $factory = new Psr17Factory();
        $request = $factory->createRequest('GET', 'https://abc.com');
        $first = (new FakeTransport())->push(new TimeoutException($request, 'slow'));
        $second = (new FakeTransport())->push(new PsrResponse(200));
        $failover = new FailoverTransport([$first, $second]);

        $this->expectException(TimeoutException::class);
        $failover->handle($request, new RequestOptions());
    }

    #[Test]
    public function testCapabilitiesAreIntersection(): void
    {
        $inner = new class implements ClientInterface {
            public function sendRequest(\Psr\Http\Message\RequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                return new PsrResponse();
            }
        };
        $failover = new FailoverTransport([new FakeTransport(), new PsrTransport($inner)]);
        $caps = $failover->capabilities();

        self::assertFalse($caps->timeout);
        self::assertFalse($caps->proxy);
    }
}
