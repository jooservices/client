<?php

declare(strict_types=1);

namespace JOOservices\Client\Tests\Unit\Transport;

use Faker\Factory;
use JOOservices\Client\Dto\RequestOptions;
use JOOservices\Client\Exceptions\NetworkConnectionException;
use JOOservices\Client\Exceptions\RequestException as JooRequestException;
use JOOservices\Client\Transport\FailoverTransport;
use JOOservices\Client\Transport\PsrTransport;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response as PsrResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class PsrTransportTest extends TestCase
{
    #[Test]
    public function testDelegatesSendRequestAndIgnoresMergedOptions(): void
    {
        $factory = new Psr17Factory();
        $inner = new class implements ClientInterface {
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                return new PsrResponse(204, ['X-Uri' => (string) $request->getUri()]);
            }
        };
        $transport = new PsrTransport($inner);
        $request = $factory->createRequest('GET', 'https://abc.com/ping');
        $response = $transport->handle($request, new RequestOptions(timeout: 99));

        self::assertSame(204, $response->getStatusCode());
        self::assertSame('https://abc.com/ping', $response->getHeaderLine('X-Uri'));
    }

    #[Test]
    public function testRestoresTheOriginalSeekableBodyPositionAfterTheClientConsumesAndFails(): void
    {
        $factory = new Psr17Factory();
        $payload = Factory::create()->sentence(8);
        $body = $factory->createStream($payload);
        $initialPosition = intdiv(strlen($payload), 3);
        $body->seek($initialPosition);
        $request = $factory->createRequest('PUT', 'https://abc.com/resource')->withBody($body);
        $inner = new class implements ClientInterface {
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $request->getBody()->getContents();

                throw new class ('offline') extends \RuntimeException implements NetworkExceptionInterface {
                    public function getRequest(): RequestInterface
                    {
                        return new Psr17Factory()->createRequest('PUT', 'https://abc.com/resource');
                    }
                };
            }
        };

        try {
            (new PsrTransport($inner))->handle($request, new RequestOptions());
            self::fail('Expected the generic client network exception to be normalized.');
        } catch (NetworkConnectionException) {
            // Expected: the transport preserves the stream even when the client fails after draining it.
        }

        self::assertSame($initialPosition, $body->tell());
        self::assertSame(substr($payload, $initialPosition), $body->getContents());
    }

    #[Test]
    public function testNormalizesARawNetworkExceptionSoFailoverStillTriggers(): void
    {
        $factory = new Psr17Factory();
        $failing = new class implements ClientInterface {
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                throw new class ('offline') extends \RuntimeException implements NetworkExceptionInterface {
                    public function getRequest(): RequestInterface
                    {
                        return new Psr17Factory()->createRequest('GET', 'https://abc.com');
                    }
                };
            }
        };
        $working = new class implements ClientInterface {
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                return new PsrResponse(200);
            }
        };

        try {
            new PsrTransport($failing)->handle($factory->createRequest('GET', 'https://abc.com'), new RequestOptions());
            self::fail('Expected a normalized NetworkConnectionException.');
        } catch (NetworkConnectionException) {
            // expected — proves it's this library's own recognized type, not the raw PSR-18 one.
        }

        $failover = new FailoverTransport([new PsrTransport($failing), new PsrTransport($working)]);
        $response = $failover->handle($factory->createRequest('GET', 'https://abc.com'), new RequestOptions());
        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function testNormalizesARawRequestException(): void
    {
        $factory = new Psr17Factory();
        $inner = new class implements ClientInterface {
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                throw new class ('malformed') extends \RuntimeException implements RequestExceptionInterface {
                    public function getRequest(): RequestInterface
                    {
                        return new Psr17Factory()->createRequest('GET', 'https://abc.com');
                    }
                };
            }
        };

        $request = $factory->createRequest('GET', 'https://abc.com');
        try {
            new PsrTransport($inner)->handle($request, new RequestOptions());
            self::fail('Expected JooRequestException.');
        } catch (JooRequestException $exception) {
            self::assertSame($request, $exception->getRequest());
        }
    }

    #[Test]
    public function testNormalizesAGenericClientException(): void
    {
        $factory = new Psr17Factory();
        $inner = new class implements ClientInterface {
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                throw new class ('generic') extends \RuntimeException implements ClientExceptionInterface {
                };
            }
        };

        $this->expectException(NetworkConnectionException::class);
        new PsrTransport($inner)->handle($factory->createRequest('GET', 'https://abc.com'), new RequestOptions());
    }
}
