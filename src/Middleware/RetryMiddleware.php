<?php

declare(strict_types=1);

namespace JOOservices\Client\Middleware;

use Closure;
use JOOservices\Client\Contracts\MiddlewareInterface;
use JOOservices\Client\Resilience\RetryConfig;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class RetryMiddleware implements MiddlewareInterface
{
    private RetryConfig $config;

    public function __construct(RetryConfig $config)
    {
        $this->config = $config;
    }

    public function __invoke(RequestInterface $request, array $options, Closure $next): ResponseInterface
    {
        $attempts = 0;
        $maxAttempts = $this->config->maxAttempts;

        while (true) {
            $attempts++;
            try {
                /** @var ResponseInterface $response */
                $response = $next($request, $options);

                // Check for retryable status codes
                if ($this->shouldRetryStatus($response->getStatusCode()) && $attempts < $maxAttempts) {
                    $this->doWait($attempts);
                    continue;
                }

                return $response;
            } catch (Throwable $e) {
                if ($attempts >= $maxAttempts || !$this->shouldRetryException($e)) {
                    throw $e;
                }

                $this->doWait($attempts);
            }
        }
    }

    private function shouldRetryStatus(int $statusCode): bool
    {
        return in_array($statusCode, $this->config->retryableStatuses, true);
    }

    private function shouldRetryException(Throwable $e): bool
    {
        foreach ($this->config->retryableExceptions as $class) {
            if ($e instanceof $class) {
                return true;
            }
        }
        return false;
    }

    private function doWait(int $attempts): void
    {
        // Exponential Backoff: base * 2^(attempts-1)
        $delay = $this->config->baseDelayMs * (2 ** ($attempts - 1));

        // Cap at max delay
        $delay = min($delay, $this->config->maxDelayMs);

        // Jitter: Randomize between 0 and calculated delay (Full Jitter)
        if ($this->config->useJitter) {
            $delay = rand(0, (int) $delay);
        }

        usleep((int) ($delay * 1000));
    }
}
