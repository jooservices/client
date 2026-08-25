<?php

declare(strict_types=1);

namespace JOOservices\Client\Middleware;

use JOOservices\Client\Contracts\MiddlewareInterface;
use JOOservices\Client\Contracts\RequestHandlerInterface;
use JOOservices\Client\Contracts\TokenProviderInterface;
use JOOservices\Client\Dto\OAuthTokenRefreshConfig;
use JOOservices\Client\Dto\RequestOptions;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class OAuthTokenRefreshMiddleware implements MiddlewareInterface
{
    private ?float $lastRefreshAt = null;

    public function __construct(private readonly TokenProviderInterface $tokens, private readonly OAuthTokenRefreshConfig $config = new OAuthTokenRefreshConfig())
    {
    }

    public function process(RequestInterface $request, RequestOptions $options, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request->withHeader($this->config->header, $this->config->prefix . ' ' . $this->tokens->token()), $options);
        if ($response->getStatusCode() !== 401) {
            return $response;
        }

        $now = microtime(true);
        if ($this->lastRefreshAt !== null && $now - $this->lastRefreshAt < $this->config->refreshCooldown) {
            // Already refreshed recently and still getting 401s — the credentials are likely
            // permanently invalid. Don't hammer the token endpoint again on every single request.
            return $response;
        }

        $this->lastRefreshAt = $now;

        return $handler->handle($request->withHeader($this->config->header, $this->config->prefix . ' ' . $this->tokens->refresh()), $options);
    }
}
