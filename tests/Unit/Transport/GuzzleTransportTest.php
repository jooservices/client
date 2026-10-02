<?php

declare(strict_types=1);

namespace JOOservices\Client\Tests\Unit\Transport;

use JOOservices\Client\Dto\RequestOptions;
use JOOservices\Client\Exceptions\NetworkConnectionException;
use JOOservices\Client\Exceptions\RequestException as JooRequestException;
use JOOservices\Client\Transport\FailoverTransport;
use JOOservices\Client\Transport\GuzzleTransport;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response as PsrResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class GuzzleTransportTest extends TestCase
{
    #[Test]
    public function testWrapsGenericFailuresAsNetworkException(): void
    {
        $factory = new Psr17Factory();
        $client = new class {
            /** @param array<string, mixed> $options */
            public function send(RequestInterface $request, array $options = []): ResponseInterface
            {
                throw new \RuntimeException('connection refused');
            }
        };
        $transport = new GuzzleTransport($client, $factory, $factory);
        $request = $factory->createRequest('GET', 'https://abc.com');

        $this->expectException(NetworkConnectionException::class);
        $this->expectExceptionMessage('connection refused');
        $transport->handle($request, new RequestOptions());
    }

    #[Test]
    public function testDisablesGuzzleHttpErrorsAndInternalRedirects(): void
    {
        $factory = new Psr17Factory();
        $client = new class {
            /** @var array<string, mixed>|null */
            public ?array $options = null;

            /** @param array<string, mixed> $options */
            public function send(RequestInterface $request, array $options = []): ResponseInterface
            {
                $this->options = $options;

                return new PsrResponse(404);
            }
        };
        $transport = new GuzzleTransport($client, $factory, $factory);
        $response = $transport->handle($factory->createRequest('GET', 'https://abc.com'), new RequestOptions(allowRedirects: true));

        self::assertSame(404, $response->getStatusCode());
        self::assertIsArray($client->options);
        self::assertFalse($client->options['http_errors']);
        self::assertFalse($client->options['allow_redirects']);
        self::assertArrayNotHasKey('decode_content', $client->options);
    }

    #[Test]
    public function testReportsCompressionCapability(): void
    {
        $factory = new Psr17Factory();
        $client = new \stdClass();

        self::assertTrue(
            (new GuzzleTransport($client, $factory, $factory))->capabilities()->compression,
        );
    }

    #[Test]
    public function testEnablesCompressionAndPreservesCallerAcceptEncoding(): void
    {
        $factory = new Psr17Factory();
        $client = new class {
            /** @var array<string, mixed>|null */
            public ?array $options = null;

            public ?RequestInterface $request = null;

            /** @param array<string, mixed> $options */
            public function send(RequestInterface $request, array $options = []): ResponseInterface
            {
                $this->request = $request;
                $this->options = $options;

                return new PsrResponse(200);
            }
        };
        $transport = new GuzzleTransport($client, $factory, $factory);
        $request = $factory->createRequest('GET', 'https://abc.com')->withHeader('Accept-Encoding', 'identity');

        $transport->handle($request, new RequestOptions(compression: true));

        self::assertIsArray($client->options);
        self::assertTrue($client->options['decode_content']);
        self::assertSame('identity', $client->request?->getHeaderLine('Accept-Encoding'));
    }

    #[Test]
    public function testAddsSupportedAcceptEncodingWhenCallerDidNotProvideOne(): void
    {
        $factory = new Psr17Factory();
        $client = new class {
            public ?RequestInterface $request = null;

            /** @param array<string, mixed> $options */
            public function send(RequestInterface $request, array $options = []): ResponseInterface
            {
                $this->request = $request;

                return new PsrResponse(200);
            }
        };
        $transport = new GuzzleTransport($client, $factory, $factory);

        $transport->handle(
            $factory->createRequest('GET', 'https://abc.com'),
            new RequestOptions(compression: true),
        );

        $acceptEncoding = $client->request?->getHeaderLine('Accept-Encoding');
        self::assertContains($acceptEncoding, ['gzip, deflate', 'gzip, deflate, br']);
    }

    #[Test]
    public function testNormalizesARawNetworkExceptionSoFailoverStillTriggers(): void
    {
        $factory = new Psr17Factory();
        $failing = new class {
            /** @param array<string, mixed> $options */
            public function send(RequestInterface $request, array $options = []): ResponseInterface
            {
                throw new class ('offline') extends \RuntimeException implements NetworkExceptionInterface {
                    public function getRequest(): RequestInterface
                    {
                        return new Psr17Factory()->createRequest('GET', 'https://abc.com');
                    }
                };
            }
        };
        $working = new class {
            /** @param array<string, mixed> $options */
            public function send(RequestInterface $request, array $options = []): ResponseInterface
            {
                return new PsrResponse(200);
            }
        };

        $failingTransport = new GuzzleTransport($failing, $factory, $factory);

        try {
            $failingTransport->handle($factory->createRequest('GET', 'https://abc.com'), new RequestOptions());
            self::fail('Expected a normalized NetworkConnectionException.');
        } catch (NetworkConnectionException) {
            // expected
        }

        $failover = new FailoverTransport([$failingTransport, new GuzzleTransport($working, $factory, $factory)]);
        $response = $failover->handle($factory->createRequest('GET', 'https://abc.com'), new RequestOptions());
        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function testNormalizesARawRequestException(): void
    {
        $factory = new Psr17Factory();
        $client = new class {
            /** @param array<string, mixed> $options */
            public function send(RequestInterface $request, array $options = []): ResponseInterface
            {
                throw new class ('malformed') extends \RuntimeException implements RequestExceptionInterface {
                    public function getRequest(): RequestInterface
                    {
                        return new Psr17Factory()->createRequest('GET', 'https://abc.com');
                    }
                };
            }
        };

        $this->expectException(JooRequestException::class);
        new GuzzleTransport($client, $factory, $factory)->handle($factory->createRequest('GET', 'https://abc.com'), new RequestOptions());
    }
}
