<?php

declare(strict_types=1);

namespace JOOservices\Client\Middleware;

use JOOservices\Client\Contracts\MetricsRecorderInterface;
use JOOservices\Client\Contracts\MiddlewareInterface;
use JOOservices\Client\Contracts\RequestHandlerInterface;
use JOOservices\Client\Dto\RequestOptions;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class MetricsMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly MetricsRecorderInterface $metrics)
    {
    }

    public function process(RequestInterface $request, RequestOptions $options, RequestHandlerInterface $handler): ResponseInterface
    {
        $started = hrtime(true);
        try {
            $response = $handler->handle($request, $options);
            $this->metrics->increment('http_client_requests_total', ['status' => $response->getStatusCode()]);

            return $response;
        } catch (\Throwable $error) {
            $this->metrics->increment('http_client_requests_total', ['status' => 'error', 'exception' => $error::class]);

            throw $error;
        } finally {
            $this->metrics->observe('http_client_duration_seconds', (hrtime(true) - $started) / 1_000_000_000);
        }
    }
}
