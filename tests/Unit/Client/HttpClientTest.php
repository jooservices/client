<?php

declare(strict_types=1);

namespace JOOservices\Client\Tests\Unit\Client;

use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Client\HttpClient;
use JOOservices\Client\Client\HttpClientSupport;
use JOOservices\Client\Dto\ClientConfig;
use JOOservices\Client\Exceptions\InvalidConfigurationException;
use JOOservices\Client\Exceptions\NetworkConnectionException;
use JOOservices\Client\Exceptions\RequestException;
use JOOservices\Client\Middleware\MiddlewarePipeline;
use JOOservices\Client\Response\Response;
use JOOservices\Client\Testing\FakeTransport;
use JOOservices\Client\Tests\Fixtures\UserDto;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response as PsrResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;

final class HttpClientTest extends TestCase
{
    #[Test]
    public function testSendRequestReturnsHttpErrorsWithoutThrowing(): void
    {
        $transport = (new FakeTransport())->push(new PsrResponse(404, [], '{"error":"missing"}'));
        $client = ClientBuilder::create()->withTransport($transport)->build();
        $request = $client->requestBuilder()->get('https://api.example.test/users/1')->toPsr();

        $response = $client->sendRequest($request);

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('missing', Response::from($response)->json()['error']);
    }

    #[Test]
    public function testSendMergesPerRequestTimeoutWithoutMutatingClient(): void
    {
        $transport = (new FakeTransport())->push(new PsrResponse())->push(new PsrResponse());
        $client = ClientBuilder::create()->withTransport($transport)->withTimeout(10)->build();
        $request = $client->requestBuilder()->get('https://api.example.test/users')->toPsr();

        $client->send($request, ['timeout' => 2]);
        $client->sendRequest($request);

        self::assertSame(2.0, $transport->recorded()[0]['options']->timeout);
        self::assertSame(10.0, $transport->recorded()[1]['options']->timeout);
    }

    #[Test]
    public function testClientUsesBaseUriAndClientHeaders(): void
    {
        $transport = (new FakeTransport())->push(new PsrResponse());
        $client = ClientBuilder::create()
            ->withTransport($transport)
            ->withBaseUri('https://api.example.test/v1')
            ->withHeader('X-App', 'client')
            ->build();

        $client->sendRequest($client->requestBuilder()->get('users')->toPsr());

        $request = $transport->recorded()[0]['request'];
        self::assertSame('https://api.example.test/v1/users', (string) $request->getUri());
        self::assertSame('client', $request->getHeaderLine('X-App'));
    }

    #[Test]
    public function testAbsolutePathReplacesBasePath(): void
    {
        $transport = (new FakeTransport())->push(new PsrResponse());
        $client = ClientBuilder::create()
            ->withTransport($transport)
            ->withBaseUri('https://api.example.test/v1')
            ->build();

        $client->sendRequest($client->requestBuilder()->get('/users')->toPsr());

        self::assertSame('https://api.example.test/users', (string) $transport->recorded()[0]['request']->getUri());
    }

    #[Test]
    public function testRelativePathKeepsABaseUriDirectoryWithoutATrailingSlash(): void
    {
        $transport = (new FakeTransport())->push(new PsrResponse());
        $factory = new Psr17Factory();
        $client = new HttpClient(
            $transport,
            new MiddlewarePipeline([], $transport),
            new ClientConfig(baseUri: 'https://site.example.test/wp-json'),
            new HttpClientSupport($factory, $factory, $factory),
        );

        $client->sendRequest($factory->createRequest('GET', 'wp/v2/posts'));

        self::assertSame(
            'https://site.example.test/wp-json/wp/v2/posts',
            (string) $transport->recorded()[0]['request']->getUri(),
        );
    }

    #[Test]
    public function testAbsoluteUriIgnoresBaseUri(): void
    {
        $transport = (new FakeTransport())->push(new PsrResponse());
        $client = ClientBuilder::create()
            ->withTransport($transport)
            ->withBaseUri('https://api.example.test')
            ->build();

        $client->sendRequest($client->requestBuilder()->get('https://other.test/x')->toPsr());

        self::assertSame('https://other.test/x', (string) $transport->recorded()[0]['request']->getUri());
    }

    #[Test]
    public function testProtocolRelativeUriIsRejected(): void
    {
        $factory = new Psr17Factory();
        $client = ClientBuilder::create()->withTransport(new FakeTransport())->withBaseUri('https://abc.com')->build();
        $request = $factory->createRequest('GET', '//evil.test/x');

        $this->expectException(RequestException::class);
        $client->sendRequest($request);
    }

    #[Test]
    public function testPsrTransportRejectsUnsupportedTimeout(): void
    {
        $inner = new class implements ClientInterface {
            public function sendRequest(\Psr\Http\Message\RequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                return new PsrResponse();
            }
        };
        $client = ClientBuilder::create()->withPsr18($inner)->build();
        $request = $client->requestBuilder()->get('https://api.example.test')->toPsr();

        $this->expectException(InvalidConfigurationException::class);
        $client->send($request, ['timeout' => 1]);
    }

    #[Test]
    public function testPsrTransportRejectsExplicitClientTimeoutAtBuild(): void
    {
        $inner = new class implements ClientInterface {
            public function sendRequest(\Psr\Http\Message\RequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                return new PsrResponse();
            }
        };

        $this->expectException(InvalidConfigurationException::class);
        ClientBuilder::create()->withPsr18($inner)->withTimeout(10)->build();
    }

    #[Test]
    public function testNetworkExceptionExposesSentRequest(): void
    {
        $factory = new Psr17Factory();
        $request = $factory->createRequest('GET', 'https://api.example.test');
        $transport = (new FakeTransport())->push(new NetworkConnectionException($request, 'offline'));
        $client = ClientBuilder::create()->withTransport($transport)->build();

        try {
            $client->sendRequest($request);
            self::fail('Expected a network exception.');
        } catch (NetworkConnectionException $error) {
            self::assertSame($request, $error->getRequest());
        }
    }

    #[Test]
    public function testVerifySslFalseIsNotOverriddenByRequestDefaults(): void
    {
        $transport = (new FakeTransport())->push(new PsrResponse());
        $client = ClientBuilder::create()->withTransport($transport)->withVerifySsl(false)->build();
        $client->sendRequest($client->requestBuilder()->get('https://api.example.test')->toPsr());

        self::assertFalse($transport->recorded()[0]['options']->verifySsl);
    }

    #[Test]
    public function testZeroTimeoutIsInvalidConfiguration(): void
    {
        $client = ClientBuilder::create()->withTransport(new FakeTransport())->build();
        $request = $client->requestBuilder()->get('https://api.example.test')->toPsr();

        $this->expectException(InvalidConfigurationException::class);
        $client->send($request, ['timeout' => 0]);
    }

    #[Test]
    public function testUnknownOptionIsRejected(): void
    {
        $client = ClientBuilder::create()->withTransport(new FakeTransport())->build();
        $request = $client->requestBuilder()->get('https://api.example.test')->toPsr();

        $this->expectException(InvalidConfigurationException::class);
        $client->send($request, ['sink' => '/tmp/x']);
    }

    #[Test]
    public function testInjectedRequestHeaderCrlfIsRejected(): void
    {
        $client = ClientBuilder::create()->withTransport(new FakeTransport())->build();
        $request = self::createStub(\Psr\Http\Message\RequestInterface::class);
        $request->method('getHeaders')->willReturn(['X-Test' => ["a\r\nInjected: 1"]]);

        $this->expectException(RequestException::class);
        $client->sendRequest($request);
    }

    #[Test]
    public function testEmptyFakeQueueThrowsNetworkException(): void
    {
        $client = ClientBuilder::create()->withTransport(new FakeTransport())->build();
        $this->expectException(NetworkConnectionException::class);
        $client->sendRequest($client->requestBuilder()->get('https://api.example.test')->toPsr());
    }

    #[Test]
    public function testThrowIsOptInOnWrapper(): void
    {
        $transport = (new FakeTransport())->push(new PsrResponse(500, [], '{"error":"nope"}'));
        $client = ClientBuilder::create()->withTransport($transport)->build();
        $psr = $client->sendRequest($client->requestBuilder()->get('https://api.example.test')->toPsr());

        $this->expectException(\JOOservices\Client\Exceptions\HttpResponseException::class);
        Response::from($psr)->throw();
    }

    #[Test]
    public function testToDtoHydratesPayload(): void
    {
        $transport = (new FakeTransport())->push(new PsrResponse(200, [], '{"name":"Viet"}'));
        $client = ClientBuilder::create()->withTransport($transport)->build();
        $user = Response::from($client->sendRequest($client->requestBuilder()->get('https://api.example.test')->toPsr()))
            ->toDto(UserDto::class);

        self::assertInstanceOf(UserDto::class, $user);
        self::assertSame('Viet', $user->name);
    }

    #[Test]
    public function testNonSeekableRequestBodyIsBufferedBeforeSigningSoItIsNotDrained(): void
    {
        $signer = new class implements \JOOservices\Client\Contracts\RequestSignerInterface {
            public ?string $seenBody = null;
            public function sign(\Psr\Http\Message\RequestInterface $request): \Psr\Http\Message\RequestInterface
            {
                $this->seenBody = (string) $request->getBody();

                return $request;
            }
        };
        $transport = (new FakeTransport())->push(new PsrResponse());
        $client = ClientBuilder::create()->withTransport($transport)->withRequestSigning($signer)->build();

        $body = new class implements \Psr\Http\Message\StreamInterface {
            private string $buffer = 'non-seekable-payload';
            private bool $consumed = false;
            public function __toString(): string
            {
                return $this->getContents();
            }
            public function close(): void
            {
            }
            public function detach()
            {
                return null;
            }
            public function getSize(): ?int
            {
                return null;
            }
            public function tell(): int
            {
                return $this->consumed ? strlen($this->buffer) : 0;
            }
            public function eof(): bool
            {
                return $this->consumed;
            }
            public function isSeekable(): bool
            {
                return false;
            }
            public function seek($offset, $whence = SEEK_SET): void
            {
                throw new \RuntimeException('Stream is not seekable.');
            }
            public function rewind(): void
            {
                throw new \RuntimeException('Stream is not seekable.');
            }
            public function isWritable(): bool
            {
                return false;
            }
            public function write($string): int
            {
                throw new \RuntimeException('Stream is not writable.');
            }
            public function isReadable(): bool
            {
                return true;
            }
            public function read($length): string
            {
                if ($this->consumed) {
                    return '';
                }
                $this->consumed = true;

                return $this->buffer;
            }
            public function getContents(): string
            {
                return $this->read(PHP_INT_MAX);
            }
            public function getMetadata($key = null)
            {
                return null;
            }
        };

        $request = $client->requestBuilder()->post('https://api.example.test')->toPsr()->withBody($body);
        $client->sendRequest($request);

        self::assertSame('non-seekable-payload', $signer->seenBody);
        self::assertSame('non-seekable-payload', (string) $transport->recorded()[0]['request']->getBody());
    }
}
