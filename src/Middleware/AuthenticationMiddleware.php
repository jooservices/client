<?php

declare(strict_types=1);

namespace JOOservices\Client\Middleware;

use JOOservices\Client\Contracts\MiddlewareInterface;
use JOOservices\Client\Contracts\RequestHandlerInterface;
use JOOservices\Client\Dto\AuthenticationConfig;
use JOOservices\Client\Dto\RequestOptions;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class AuthenticationMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly AuthenticationConfig $config)
    {
    }

    public function process(RequestInterface $request, RequestOptions $options, RequestHandlerInterface $handler): ResponseInterface
    {
        if (! $request->hasHeader($this->config->header)) {
            $value = match ($this->config->type) {
                'bearer' => trim($this->config->prefix . ' ' . $this->config->value),
                'basic' => 'Basic ' . base64_encode($this->config->value),
                default => $this->config->value,
            };
            $request = $request->withHeader($this->config->header, $value);
        }

        return $handler->handle($request, $options);
    }
}
