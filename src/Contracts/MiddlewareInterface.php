<?php

declare(strict_types=1);

namespace JOOservices\Client\Contracts;

use Closure;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

interface MiddlewareInterface
{
    /**
     * Process the request and return a response.
     *
     * @param RequestInterface $request
     * @param array<string, mixed> $options
     * @param Closure(RequestInterface, array<string, mixed>): ResponseInterface $next
     * @return ResponseInterface
     */
    public function __invoke(RequestInterface $request, array $options, Closure $next): ResponseInterface;
}
