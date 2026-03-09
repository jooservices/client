<?php

declare(strict_types=1);

namespace JOOservices\Client\Middleware;

use Closure;
use GuzzleHttp\Psr7\Response;
use JOOservices\Client\Contracts\MiddlewareInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\SimpleCache\CacheInterface;

class CacheMiddleware implements MiddlewareInterface
{
    private CacheInterface $cache;
    private int $defaultTtl;

    public function __construct(CacheInterface $cache, int $defaultTtl = 3600)
    {
        $this->cache = $cache;
        $this->defaultTtl = $defaultTtl;
    }

    public function __invoke(RequestInterface $request, array $options, Closure $next): ResponseInterface
    {
        // Only cache GET requests
        if ($request->getMethod() !== 'GET') {
            return $next($request, $options);
        }

        $cacheKey = $this->generateCacheKey($request);

        // Try Cache
        $cachedValue = $this->cache->get($cacheKey);
        $hasCacheShape = $cachedValue !== null
            && is_array($cachedValue)
            && isset($cachedValue['status'], $cachedValue['headers'], $cachedValue['body']);
        if ($hasCacheShape) {
            // Reconstruct Response (Simplified: body, status, headers)
            // Note: PSR-7 streams cannot be easily serialized.
            // We assume cached value is [status, headers, bodyString]
            $status = is_int($cachedValue['status']) ? $cachedValue['status'] : 200;
            /** @var array<array<string>|string> $headers */
            $headers = is_array($cachedValue['headers']) ? $cachedValue['headers'] : [];
            $body = is_string($cachedValue['body']) ? $cachedValue['body'] : '';
            return new Response($status, $headers, $body);
        }

        /** @var ResponseInterface $response */
        $response = $next($request, $options);

        // Cache Success Responses (200)
        if ($response->getStatusCode() === 200) {
            $body = (string) $response->getBody();
            // Important: Rewind body so downstream can use it!
            $response->getBody()->rewind();

            $value = [
                'status' => $response->getStatusCode(),
                'headers' => $response->getHeaders(),
                'body' => $body,
            ];

            // Determine TTL from options or default
            $ttlOption = $options['cache_ttl'] ?? $this->defaultTtl;
            $ttl = is_int($ttlOption) || $ttlOption instanceof \DateInterval ? $ttlOption : $this->defaultTtl;

            $this->cache->set($cacheKey, $value, $ttl);
        }

        return $response;
    }

    private function generateCacheKey(RequestInterface $request): string
    {
        return 'http_cache_' . md5($request->getMethod() . ' ' . $request->getUri());
    }
}
