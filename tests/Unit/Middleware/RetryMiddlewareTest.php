<?php

declare(strict_types=1);

namespace JOOservices\Client\Tests\Unit\Middleware;

use Faker\Factory;
use JOOservices\Client\Contracts\RequestHandlerInterface;
use JOOservices\Client\Dto\RequestOptions;
use JOOservices\Client\Exceptions\NetworkConnectionException;
use JOOservices\Client\Exceptions\RequestException;
use JOOservices\Client\Middleware\RetryMiddleware;
use JOOservices\Client\Resilience\RetryConfig;
use JOOservices\Client\Support\NullSleeper;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

final class RetryMiddlewareTest extends TestCase
{
    #[Test]
    public function testReplaysTheOriginalSeekableBodyAfterANetworkFailure(): void
    {
        $payload = Factory::create()->sentence(8);
        $request = (new Psr17Factory())->createRequest('PUT', 'https://abc.com/resource')
            ->withBody((new Psr17Factory())->createStream($payload));
        $handler = new class implements RequestHandlerInterface {
            /** @var list<string> */
            public array $bodies = [];

            public function handle(RequestInterface $request, RequestOptions $options): ResponseInterface
            {
                $this->bodies[] = $request->getBody()->getContents();
                if (count($this->bodies) === 1) {
                    throw new NetworkConnectionException($request, 'Temporary network failure.');
                }

                return new Response(200);
            }
        };

        $response = (new RetryMiddleware(new RetryConfig(maxAttempts: 2), new NullSleeper()))
            ->process($request, new RequestOptions(), $handler);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([$payload, $payload], $handler->bodies);
    }

    #[Test]
    public function testReplaysTheOriginalSeekableBodyAfterARetryableStatus(): void
    {
        $payload = Factory::create()->sentence(8);
        $request = (new Psr17Factory())->createRequest('PUT', 'https://abc.com/resource')
            ->withBody((new Psr17Factory())->createStream($payload));
        $handler = new class implements RequestHandlerInterface {
            /** @var list<string> */
            public array $bodies = [];

            public function handle(RequestInterface $request, RequestOptions $options): ResponseInterface
            {
                $this->bodies[] = $request->getBody()->getContents();

                return new Response(count($this->bodies) === 1 ? 503 : 200);
            }
        };

        $response = (new RetryMiddleware(new RetryConfig(maxAttempts: 2), new NullSleeper()))
            ->process($request, new RequestOptions(), $handler);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([$payload, $payload], $handler->bodies);
    }

    #[Test]
    public function testFailsSafelyWhenTheRequestBodyCannotBeReplayed(): void
    {
        $payload = Factory::create()->sentence(8);
        $body = self::createStub(StreamInterface::class);
        $body->method('isSeekable')->willReturn(false);
        $body->method('getContents')->willReturn($payload);
        $request = (new Psr17Factory())->createRequest('PUT', 'https://abc.com/resource')->withBody($body);
        $handler = new class implements RequestHandlerInterface {
            public int $calls = 0;

            public function handle(RequestInterface $request, RequestOptions $options): ResponseInterface
            {
                ++$this->calls;
                $request->getBody()->getContents();

                throw new NetworkConnectionException($request, 'Temporary network failure.');
            }
        };

        try {
            (new RetryMiddleware(new RetryConfig(maxAttempts: 2), new NullSleeper()))
                ->process($request, new RequestOptions(), $handler);
            self::fail('Expected a RequestException when the body cannot be replayed.');
        } catch (RequestException $exception) {
            self::assertInstanceOf(NetworkConnectionException::class, $exception->getPrevious());
        }

        self::assertSame(1, $handler->calls);
    }
}
