<?php

declare(strict_types=1);

namespace JOOservices\Client\Tests\Unit\Middleware;

use JOOservices\Client\Contracts\RequestHandlerInterface;
use JOOservices\Client\Contracts\MiddlewareInterface;
use JOOservices\Client\Contracts\SleeperInterface;
use JOOservices\Client\Dto\RequestOptions;
use JOOservices\Client\Exceptions\TimeoutException;
use JOOservices\Client\Middleware\DeadlineMiddleware;
use JOOservices\Client\Middleware\MiddlewarePipeline;
use JOOservices\Client\Middleware\RetryMiddleware;
use JOOservices\Client\Resilience\RetryConfig;
use JOOservices\Client\Support\NullSleeper;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class DeadlineMiddlewareTest extends TestCase
{
    #[Test]
    public function testDeadlineCompletesASingleAttemptWithoutRetryMiddleware(): void
    {
        $handler = new class implements RequestHandlerInterface {
            public int $calls = 0;

            public function handle(RequestInterface $request, RequestOptions $options): ResponseInterface
            {
                ++$this->calls;

                return new Response(200);
            }
        };

        $response = (new DeadlineMiddleware(1.0))->process($this->request(), new RequestOptions(), $handler);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(1, $handler->calls);
    }

    #[Test]
    public function testPipelineDoesNotStartTerminalHandlerAfterAnInnerMiddlewareExhaustsDeadline(): void
    {
        $delayed = new class implements MiddlewareInterface {
            public function process(RequestInterface $request, RequestOptions $options, RequestHandlerInterface $handler): ResponseInterface
            {
                usleep(30_000);

                return $handler->handle($request, $options);
            }
        };
        $terminal = new class implements RequestHandlerInterface {
            public int $calls = 0;

            public function handle(RequestInterface $request, RequestOptions $options): ResponseInterface
            {
                ++$this->calls;

                return new Response(200);
            }
        };
        $pipeline = new MiddlewarePipeline([new DeadlineMiddleware(0.01), $delayed], $terminal);

        try {
            $pipeline->handle($this->request(), new RequestOptions(timeout: 1.0));
            self::fail('Expected the client deadline to expire before the terminal handler.');
        } catch (TimeoutException) {
            self::assertSame(0, $terminal->calls);
        }
    }

    #[Test]
    public function testPipelineClampsTimeoutAtTheTerminalBoundaryAfterAnInnerDelay(): void
    {
        $delayed = new class implements MiddlewareInterface {
            public function process(RequestInterface $request, RequestOptions $options, RequestHandlerInterface $handler): ResponseInterface
            {
                usleep(100_000);

                return $handler->handle($request, $options);
            }
        };
        $terminal = new class implements RequestHandlerInterface {
            public ?float $timeout = null;

            public function handle(RequestInterface $request, RequestOptions $options): ResponseInterface
            {
                $this->timeout = $options->timeout;

                return new Response(200);
            }
        };
        $pipeline = new MiddlewarePipeline([new DeadlineMiddleware(0.3), $delayed], $terminal);

        $response = $pipeline->handle($this->request(), new RequestOptions(timeout: 1.0));

        self::assertSame(200, $response->getStatusCode());
        self::assertNotNull($terminal->timeout);
        self::assertGreaterThan(0.0, $terminal->timeout);
        self::assertLessThan(0.25, $terminal->timeout);
    }

    #[Test]
    public function testSingleSuccessfulAttemptCompletesWithinDeadlineAndKeepsCallerTimeout(): void
    {
        $handler = new class implements RequestHandlerInterface {
            /** @var list<float|null> */
            public array $timeouts = [];

            /** @var list<array<string, mixed>> */
            public array $extras = [];

            public function handle(RequestInterface $request, RequestOptions $options): ResponseInterface
            {
                $this->timeouts[] = $options->timeout;
                $this->extras[] = $options->extra;

                return new Response(200);
            }
        };

        $response = $this->pipeline(
            new DeadlineMiddleware(1.0),
            new RetryConfig(maxAttempts: 3, delayMilliseconds: 10),
            new NullSleeper(),
            $handler,
        )->handle($this->request(), new RequestOptions(timeout: 0.25));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([0.25], $handler->timeouts);
        self::assertSame([[]], $handler->extras);
    }

    #[Test]
    public function testAllowsMultipleFailingAttemptsAndBackoffsWithinDeadline(): void
    {
        $handler = new class implements RequestHandlerInterface {
            public int $calls = 0;

            public function handle(RequestInterface $request, RequestOptions $options): ResponseInterface
            {
                ++$this->calls;

                return new Response($this->calls < 3 ? 503 : 200);
            }
        };
        $sleeper = new class implements SleeperInterface {
            /** @var list<int> */
            public array $delays = [];

            public function sleep(int $milliseconds): void
            {
                $this->delays[] = $milliseconds;
            }
        };

        $response = $this->pipeline(
            new DeadlineMiddleware(1.0),
            new RetryConfig(maxAttempts: 3, delayMilliseconds: 10),
            $sleeper,
            $handler,
        )->handle($this->request(), new RequestOptions());

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(3, $handler->calls);
        self::assertSame([10, 10], $sleeper->delays);
    }

    #[Test]
    public function testExpiryDuringAnAttemptPreventsAnotherAttempt(): void
    {
        $handler = new class implements RequestHandlerInterface {
            public int $calls = 0;

            public function handle(RequestInterface $request, RequestOptions $options): ResponseInterface
            {
                ++$this->calls;
                usleep(30_000);

                return new Response(503);
            }
        };

        try {
            $this->pipeline(
                new DeadlineMiddleware(0.01),
                new RetryConfig(maxAttempts: 3, delayMilliseconds: 1),
                new NullSleeper(),
                $handler,
            )->handle($this->request(), new RequestOptions());
            self::fail('Expected the client deadline to expire during the attempt.');
        } catch (TimeoutException) {
            self::assertSame(1, $handler->calls);
        }
    }

    #[Test]
    public function testExpiryDuringBackoffPreventsAnotherAttempt(): void
    {
        $handler = new class implements RequestHandlerInterface {
            public int $calls = 0;

            public function handle(RequestInterface $request, RequestOptions $options): ResponseInterface
            {
                ++$this->calls;

                return new Response(503);
            }
        };
        $sleeper = new class implements SleeperInterface {
            public function sleep(int $milliseconds): void
            {
                usleep(($milliseconds + 20) * 1_000);
            }
        };

        try {
            $this->pipeline(
                new DeadlineMiddleware(0.01),
                new RetryConfig(maxAttempts: 3, delayMilliseconds: 500),
                $sleeper,
                $handler,
            )->handle($this->request(), new RequestOptions());
            self::fail('Expected the client deadline to expire during backoff.');
        } catch (TimeoutException) {
            self::assertSame(1, $handler->calls);
        }
    }

    #[Test]
    public function testEachAttemptTimeoutUsesTheRemainingDeadlineBudget(): void
    {
        $handler = new class implements RequestHandlerInterface {
            /** @var list<float|null> */
            public array $timeouts = [];

            public function handle(RequestInterface $request, RequestOptions $options): ResponseInterface
            {
                $this->timeouts[] = $options->timeout;

                return new Response(count($this->timeouts) === 1 ? 503 : 200);
            }
        };
        $sleeper = new class implements SleeperInterface {
            public function sleep(int $milliseconds): void
            {
                usleep($milliseconds * 1_000);
            }
        };

        $response = $this->pipeline(
            new DeadlineMiddleware(0.5),
            new RetryConfig(maxAttempts: 2, delayMilliseconds: 300),
            $sleeper,
            $handler,
        )->handle($this->request(), new RequestOptions(timeout: 2.0));

        self::assertSame(200, $response->getStatusCode());
        self::assertCount(2, $handler->timeouts);
        self::assertNotNull($handler->timeouts[0]);
        self::assertNotNull($handler->timeouts[1]);
        self::assertLessThanOrEqual(0.5, $handler->timeouts[0]);
        self::assertGreaterThan(0.0, $handler->timeouts[1]);
        self::assertLessThan(0.25, $handler->timeouts[1]);
    }

    private function pipeline(
        DeadlineMiddleware $deadline,
        RetryConfig $config,
        SleeperInterface $sleeper,
        RequestHandlerInterface $handler,
    ): MiddlewarePipeline {
        return new MiddlewarePipeline([$deadline, new RetryMiddleware($config, $sleeper)], $handler);
    }

    private function request(): RequestInterface
    {
        return (new Psr17Factory())->createRequest('GET', 'https://deadline.example.test/resource');
    }
}
