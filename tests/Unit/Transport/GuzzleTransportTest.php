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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

require_once __DIR__ . '/../../Fixtures/redirect-target-dns-stub.php';

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
    public function testFailsClosedBeforeFollowingCrossHostRedirectWhenDnsPinsAreRequired(): void
    {
        $factory = new Psr17Factory();
        $client = new class {
            /** @var list<RequestInterface> */
            public array $requests = [];

            /** @param array<string, mixed> $options */
            public function send(RequestInterface $request, array $options = []): ResponseInterface
            {
                $this->requests[] = $request;

                return count($this->requests) === 1
                    ? new PsrResponse(302, ['Location' => 'https://example.com/to'])
                    : new PsrResponse(200);
            }
        };
        $transport = new GuzzleTransport($client, $factory, $factory);

        try {
            $transport->handle(
                $factory->createRequest('GET', 'https://abc.com/from'),
                new RequestOptions(allowRedirects: true),
            );
            self::fail('Expected a cross-host redirect that requires DNS pinning to fail closed.');
        } catch (JooRequestException $exception) {
            self::assertStringContainsString('DNS pinning', $exception->getMessage());
        }

        self::assertCount(1, $client->requests);
        self::assertSame('https://abc.com/from', (string) $client->requests[0]->getUri());
    }

    /** @param bool|array<string, mixed>|null $allowRedirects */
    #[Test]
    #[DataProvider('unpinnedCrossHostRedirects')]
    public function testFollowsCrossHostRedirectsWhenDnsPinningIsNotRequired(string $location, bool|array|null $allowRedirects): void
    {
        $factory = new Psr17Factory();
        $client = new class {
            /** @var list<RequestInterface> */
            public array $requests = [];

            public string $redirectTarget = '';

            /** @param array<string, mixed> $options */
            public function send(RequestInterface $request, array $options = []): ResponseInterface
            {
                $this->requests[] = $request;

                return count($this->requests) === 1
                    ? new PsrResponse(302, ['Location' => $this->redirectTarget])
                    : new PsrResponse(200);
            }
        };
        $client->redirectTarget = $location;
        $transport = new GuzzleTransport($client, $factory, $factory);

        $response = $transport->handle(
            $factory->createRequest('GET', 'https://abc.com/from'),
            new RequestOptions(allowRedirects: $allowRedirects),
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertCount(2, $client->requests);
        self::assertSame($location, (string) $client->requests[1]->getUri());
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

    /** @return iterable<string, array{string, bool|array<string, mixed>|null}> */
    public static function unpinnedCrossHostRedirects(): iterable
    {
        yield 'literal public address' => ['https://93.184.216.34/to', true];
        yield 'explicit private target opt-in' => ['https://private.example/to', ['allow_private' => true]];
    }
}
