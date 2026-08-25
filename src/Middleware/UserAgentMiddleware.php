<?php

declare(strict_types=1);

namespace JOOservices\Client\Middleware;

use JOOservices\Client\Contracts\MiddlewareInterface;
use JOOservices\Client\Contracts\RequestHandlerInterface;
use JOOservices\Client\Dto\RequestOptions;
use JOOservices\Client\Exceptions\InvalidConfigurationException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class UserAgentMiddleware implements MiddlewareInterface
{
    /** @param string|list<string> $userAgent A single fixed value, or a pool to pick from at random on every request. */
    public function __construct(private readonly string|array $userAgent)
    {
        if ($this->userAgent === []) {
            throw new InvalidConfigurationException('UserAgentMiddleware requires at least one user agent.');
        }
    }

    public function process(RequestInterface $request, RequestOptions $options, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->hasHeader('User-Agent')) {
            return $handler->handle($request, $options);
        }

        $value = is_array($this->userAgent) ? $this->userAgent[array_rand($this->userAgent)] : $this->userAgent;

        return $handler->handle($request->withHeader('User-Agent', $value), $options);
    }
}
