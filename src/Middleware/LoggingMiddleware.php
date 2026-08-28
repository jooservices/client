<?php

declare(strict_types=1);

namespace JOOservices\Client\Middleware;

use JOOservices\Client\Contracts\MiddlewareInterface;
use JOOservices\Client\Contracts\RequestHandlerInterface;
use JOOservices\Client\Dto\RequestOptions;
use JOOservices\Client\Logging\LogSanitizer;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Throwable;

final class LoggingMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly LoggerInterface $logger, private readonly LogSanitizer $sanitizer = new LogSanitizer())
    {
    }

    public function process(RequestInterface $request, RequestOptions $options, RequestHandlerInterface $handler): ResponseInterface
    {
        $context = $this->sanitizer->sanitize(['method' => $request->getMethod(), 'uri' => (string) $request->getUri(), 'headers' => $request->getHeaders()]);
        $this->logger->debug('HTTP client request', $context);
        if ($options->verifySsl === false) {
            // Silent by default otherwise: a caller who disables TLS verification (intentional for a
            // local/dev target, a mistake in production) gets no signal either way unless they're
            // already logging at debug level and reading closely.
            $this->logger->warning('HTTP client request has TLS certificate verification disabled', ['uri' => $context['uri']]);
        }
        try {
            $response = $handler->handle($request, $options);
            $this->logger->debug('HTTP client response', ['status' => $response->getStatusCode(), 'uri' => $context['uri']]);

            return $response;
        } catch (Throwable $error) {
            $this->logger->error('HTTP client failure', $this->sanitizer->sanitize(['error' => $error->getMessage(), 'uri' => $context['uri']]));
            throw $error;
        }
    }
}
