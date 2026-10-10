<?php

declare(strict_types=1);

namespace JOOservices\Client\Tests\Unit\Transport;

use Faker\Factory;
use JOOservices\Client\Contracts\TransportCapabilities;
use JOOservices\Client\Contracts\TransportInterface;
use JOOservices\Client\Dto\RequestOptions;
use JOOservices\Client\Exceptions\InvalidConfigurationException;
use JOOservices\Client\Exceptions\NetworkConnectionException;
use JOOservices\Client\Exceptions\RequestException;
use JOOservices\Client\Exceptions\TimeoutException;
use JOOservices\Client\Testing\FakeTransport;
use JOOservices\Client\Transport\FailoverTransport;
use JOOservices\Client\Transport\PsrTransport;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response as PsrResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

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
    public function testReplaysTheOriginalSeekableBodyBeforeTheNextTransport(): void
    {
        $factory = new Psr17Factory();
        $payload = Factory::create()->sentence(8);
        $request = $factory->createRequest('PUT', 'https://abc.com/resource')
            ->withBody($factory->createStream($payload));
        $first = new class implements TransportInterface {
            /** @var list<string> */
            public array $bodies = [];

            public function capabilities(): TransportCapabilities
            {
                return new TransportCapabilities();
            }

            public function handle(RequestInterface $request, RequestOptions $options): ResponseInterface
            {
                $this->bodies[] = $request->getBody()->getContents();

                throw new NetworkConnectionException($request, 'First transport failed.');
            }
        };
        $second = new class implements TransportInterface {
            /** @var list<string> */
            public array $bodies = [];

            public function capabilities(): TransportCapabilities
            {
                return new TransportCapabilities();
            }

            public function handle(RequestInterface $request, RequestOptions $options): ResponseInterface
            {
                $this->bodies[] = $request->getBody()->getContents();

                return new PsrResponse(200);
            }
        };

        $response = (new FailoverTransport([$first, $second]))->handle($request, new RequestOptions());

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([$payload], $first->bodies);
        self::assertSame([$payload], $second->bodies);
    }

    #[Test]
    public function testDoesNotFailOverWhenTheRequestBodyCannotBeReplayed(): void
    {
        $factory = new Psr17Factory();
        $payload = Factory::create()->sentence(8);
        $body = self::createStub(\Psr\Http\Message\StreamInterface::class);
        $body->method('isSeekable')->willReturn(false);
        $body->method('getContents')->willReturn($payload);
        $request = $factory->createRequest('PUT', 'https://abc.com/resource')->withBody($body);
        $first = new class implements TransportInterface {
            public function capabilities(): TransportCapabilities
            {
                return new TransportCapabilities();
            }

            public function handle(RequestInterface $request, RequestOptions $options): ResponseInterface
            {
                $request->getBody()->getContents();

                throw new NetworkConnectionException($request, 'First transport failed.');
            }
        };
        $second = $this->createMock(TransportInterface::class);
        $second->expects(self::never())->method('handle');

        try {
            (new FailoverTransport([$first, $second]))->handle($request, new RequestOptions());
            self::fail('Expected a RequestException when the body cannot be replayed.');
        } catch (RequestException $exception) {
            self::assertInstanceOf(NetworkConnectionException::class, $exception->getPrevious());
        }
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
    public function testRejectsAnEmptyTransportList(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        new FailoverTransport([]);
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
