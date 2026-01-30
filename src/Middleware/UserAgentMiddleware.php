<?php

declare(strict_types=1);

namespace JOOservices\Client\Middleware;

use Closure;
use JOOservices\Client\Contracts\MiddlewareInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class UserAgentMiddleware implements MiddlewareInterface
{
    private string $userAgent;

    public function __construct(string $userAgent = 'JOOClient/2.0')
    {
        $this->userAgent = $userAgent;
    }

    public function __invoke(RequestInterface $request, array $options, Closure $next): ResponseInterface
    {
        if (!$request->hasHeader('User-Agent') || trim($request->getHeaderLine('User-Agent')) === '') {
            $request = $request->withHeader('User-Agent', $this->userAgent);
        }

        return $next($request, $options);
    }
}
