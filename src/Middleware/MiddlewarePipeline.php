<?php

declare(strict_types=1);

namespace JOOservices\Client\Middleware;

use JOOservices\Client\Contracts\MiddlewareInterface;
use JOOservices\Client\Contracts\RequestHandlerInterface;
use JOOservices\Client\Dto\RequestOptions;
use JOOservices\Client\Exceptions\TimeoutException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class MiddlewarePipeline implements RequestHandlerInterface
{
    /** @var RequestHandlerInterface */
    private RequestHandlerInterface $compiled;

    /** @param list<MiddlewareInterface> $middlewares */
    public function __construct(array $middlewares, RequestHandlerInterface $terminal)
    {
        $boundedTerminal = new class ($terminal) implements RequestHandlerInterface {
            private const DEADLINE_OPTION = '__joo_client_deadline_ns';

            public function __construct(private readonly RequestHandlerInterface $terminal)
            {
            }

            public function handle(RequestInterface $request, RequestOptions $options): ResponseInterface
            {
                $deadline = $options->extra[self::DEADLINE_OPTION] ?? null;
                if (! is_int($deadline)) {
                    return $this->terminal->handle($request, $options);
                }

                $remaining = ($deadline - hrtime(true)) / 1_000_000_000;
                if ($remaining <= 0.0) {
                    throw new TimeoutException($request, 'The client deadline elapsed.');
                }

                $extra = $options->extra;
                unset($extra[self::DEADLINE_OPTION]);

                $allowRedirects = $options->allowRedirects;
                if (is_array($allowRedirects)) {
                    $totalTimeout = $allowRedirects['total_timeout'] ?? null;
                    if (is_numeric($totalTimeout) && (float) $totalTimeout > 0.0) {
                        $allowRedirects['total_timeout'] = min((float) $totalTimeout, $remaining);
                    }
                }

                return $this->terminal->handle($request, new RequestOptions(
                    timeout: min($options->timeout ?? $remaining, $remaining),
                    connectTimeout: $options->connectTimeout,
                    proxy: $options->proxy,
                    verifySsl: $options->verifySsl,
                    allowRedirects: $allowRedirects,
                    extra: $extra,
                    compression: $options->compression,
                ));
            }
        };

        $this->compiled = array_reduce(
            array_reverse($middlewares),
            static fn(RequestHandlerInterface $next, MiddlewareInterface $middleware): RequestHandlerInterface => new class ($middleware, $next) implements RequestHandlerInterface {
                public function __construct(private readonly MiddlewareInterface $middleware, private readonly RequestHandlerInterface $next)
                {
                }
                public function handle(RequestInterface $request, RequestOptions $options): ResponseInterface
                {
                    return $this->middleware->process($request, $options, $this->next);
                }
            },
            $boundedTerminal,
        );
    }

    public function handle(RequestInterface $request, RequestOptions $options): ResponseInterface
    {
        return $this->compiled->handle($request, $options);
    }
}
