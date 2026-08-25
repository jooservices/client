<?php

declare(strict_types=1);

namespace JOOservices\Client\Middleware;

use JOOservices\Client\Contracts\MiddlewareInterface;
use JOOservices\Client\Contracts\RequestHandlerInterface;
use JOOservices\Client\Dto\RequestOptions;
use JOOservices\Client\Exceptions\TimeoutException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class DeadlineMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly float $seconds)
    {
    }

    public function process(RequestInterface $request, RequestOptions $options, RequestHandlerInterface $handler): ResponseInterface
    {
        $started = hrtime(true);

        try {
            $response = $handler->handle($request, new RequestOptions(
                min($options->timeout ?? $this->seconds, $this->seconds),
                $options->connectTimeout,
                $options->proxy,
                $options->verifySsl,
                $options->allowRedirects,
                $options->extra,
            ));
        } catch (\Throwable $error) {
            // The per-attempt timeout above only bounds a single HTTP attempt; a retry/backoff sequence
            // wrapped by this middleware can still blow past the deadline before finally throwing. Make
            // sure that's surfaced here too, not just on the success path.
            if ($this->elapsedSeconds($started) > $this->seconds) {
                throw new TimeoutException($request, 'The client deadline elapsed.', $error);
            }

            throw $error;
        }

        if ($this->elapsedSeconds($started) > $this->seconds) {
            throw new TimeoutException($request, 'The client deadline elapsed.');
        }

        return $response;
    }

    private function elapsedSeconds(int $started): float
    {
        return (hrtime(true) - $started) / 1_000_000_000;
    }
}
