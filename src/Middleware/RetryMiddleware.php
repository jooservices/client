<?php

declare(strict_types=1);

namespace JOOservices\Client\Middleware;

use JOOservices\Client\Contracts\MiddlewareInterface;
use JOOservices\Client\Contracts\RequestHandlerInterface;
use JOOservices\Client\Contracts\SleeperInterface;
use JOOservices\Client\Dto\RequestOptions;
use JOOservices\Client\Exceptions\NetworkConnectionException;
use JOOservices\Client\Resilience\RetryConfig;
use JOOservices\Client\Support\RetryAfterHeader;
use JOOservices\Client\Support\UsleepSleeper;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

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
        while (true) {
            try {
                $response = $handler->handle($request, $options);
            } catch (NetworkConnectionException $error) {
                if (! $this->canRetry($request, $attempt)) {
                    throw $error;
                }

                $this->pause($this->config->delayMilliseconds);
                ++$attempt;

                continue;
            }

            if (! in_array($response->getStatusCode(), $this->config->statuses, true) || ! $this->canRetry($request, $attempt)) {
                return $response;
            }

            $this->pause($this->retryAfter->milliseconds($response->getHeaderLine('Retry-After'), $this->config->delayMilliseconds));
            ++$attempt;
        }
    }

    private function canRetry(RequestInterface $request, int $attempt): bool
    {
        return $attempt < max(1, $this->config->maxAttempts)
            && in_array(strtoupper($request->getMethod()), $this->config->methods, true);
    }

    private function pause(int $milliseconds): void
    {
        $this->sleeper->sleep(max(0, $milliseconds));
    }
}
