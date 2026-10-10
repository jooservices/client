<?php

declare(strict_types=1);

namespace JOOservices\Client\Middleware;

use JOOservices\Client\Contracts\MiddlewareInterface;
use JOOservices\Client\Contracts\RequestHandlerInterface;
use JOOservices\Client\Contracts\SleeperInterface;
use JOOservices\Client\Dto\RequestOptions;
use JOOservices\Client\Exceptions\NetworkConnectionException;
use JOOservices\Client\Exceptions\RequestException;
use JOOservices\Client\Exceptions\TimeoutException;
use JOOservices\Client\Resilience\RetryConfig;
use JOOservices\Client\Support\RetryAfterHeader;
use JOOservices\Client\Support\UsleepSleeper;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

final class RetryMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly RetryConfig $config,
        private readonly SleeperInterface $sleeper = new UsleepSleeper(),
        private readonly RetryAfterHeader $retryAfter = new RetryAfterHeader(),
    ) {
    }

    public function process(RequestInterface $request, RequestOptions $options, RequestHandlerInterface $handler): ResponseInterface
    {
        $attempt = 1;
        $bodyPosition = $this->bodyPosition($request);
        $deadline = $this->deadline($options);
        while (true) {
            $attemptOptions = $this->attemptOptions($request, $options, $deadline);
            try {
                $response = $handler->handle($request, $attemptOptions);
            } catch (NetworkConnectionException $error) {
                $this->remainingSeconds($request, $deadline, $error);
                if (! $this->canRetry($request, $attempt)) {
                    throw $error;
                }

                $this->restoreBodyPosition($request, $bodyPosition, $error);
                $this->pause($this->config->delayMilliseconds, $request, $deadline);
                ++$attempt;

                continue;
            }

            if (! in_array($response->getStatusCode(), $this->config->statuses, true) || ! $this->canRetry($request, $attempt)) {
                return $response;
            }

            $this->remainingSeconds($request, $deadline);
            $this->restoreBodyPosition($request, $bodyPosition);
            $this->pause(
                $this->retryAfter->milliseconds($response->getHeaderLine('Retry-After'), $this->config->delayMilliseconds),
                $request,
                $deadline,
            );
            ++$attempt;
        }
    }

    private function deadline(RequestOptions $options): ?int
    {
        $deadline = $options->extra[DeadlineOption::KEY] ?? null;

        return is_int($deadline) ? $deadline : null;
    }

    private function attemptOptions(RequestInterface $request, RequestOptions $options, ?int $deadline): RequestOptions
    {
        $remaining = $this->remainingSeconds($request, $deadline);
        if ($remaining === null) {
            return $options;
        }

        $allowRedirects = $options->allowRedirects;
        if (is_array($allowRedirects)) {
            $totalTimeout = $allowRedirects['total_timeout'] ?? null;
            if (is_numeric($totalTimeout) && (float) $totalTimeout > 0.0) {
                $allowRedirects['total_timeout'] = min((float) $totalTimeout, $remaining);
            }
        }

        return new RequestOptions(
            timeout: min($options->timeout ?? $remaining, $remaining),
            connectTimeout: $options->connectTimeout,
            proxy: $options->proxy,
            verifySsl: $options->verifySsl,
            allowRedirects: $allowRedirects,
            extra: $options->extra,
            compression: $options->compression,
        );
    }

    private function remainingSeconds(RequestInterface $request, ?int $deadline, ?Throwable $previous = null): ?float
    {
        if ($deadline === null) {
            return null;
        }

        $remaining = ($deadline - hrtime(true)) / 1_000_000_000;
        if ($remaining <= 0.0) {
            throw new TimeoutException($request, 'The client deadline elapsed.', $previous);
        }

        return $remaining;
    }

    private function bodyPosition(RequestInterface $request): ?int
    {
        $body = $request->getBody();
        try {
            return $body->isSeekable() ? $body->tell() : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function restoreBodyPosition(RequestInterface $request, ?int $position, ?Throwable $previous = null): void
    {
        if ($position === null) {
            throw new RequestException($request, 'The request body cannot be replayed safely.', $previous);
        }

        $body = $request->getBody();
        try {
            if (! $body->isSeekable()) {
                throw new RequestException($request, 'The request body cannot be replayed safely.', $previous);
            }
            $body->seek($position);
        } catch (RequestException $error) {
            throw $error;
        } catch (Throwable $error) {
            throw new RequestException($request, 'Unable to rewind the request body for retry.', $error);
        }
    }

    private function canRetry(RequestInterface $request, int $attempt): bool
    {
        return $attempt < max(1, $this->config->maxAttempts)
            && in_array(strtoupper($request->getMethod()), $this->config->methods, true);
    }

    private function pause(int $milliseconds, RequestInterface $request, ?int $deadline): void
    {
        $delay = max(0, $milliseconds);
        $remaining = $this->remainingSeconds($request, $deadline);
        if ($remaining !== null) {
            $delay = min($delay, max(0, (int) floor($remaining * 1_000)));
        }

        $this->sleeper->sleep($delay);
        $this->remainingSeconds($request, $deadline);
    }
}
