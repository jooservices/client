<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use JOOservices\Client\Middleware\CacheMiddleware;
use Psr\SimpleCache\CacheInterface;

describe('CacheMiddleware', function () {
    it('caches GET requests', function () {
        $cache = Mockery::mock(CacheInterface::class);
        $middleware = new CacheMiddleware($cache);

        $request = new Request('GET', 'https://example.com');
        $response = new Response(200, ['X-Foo' => 'Bar'], 'body content');

        // Expect cache get
        $cache->shouldReceive('get')->once()->andReturn(null);

        // Expect cache set with correct structure
        $cache->shouldReceive('set')->once()->withArgs(function ($key, $value, $ttl) {
            return str_contains($key, 'http_cache_') &&
                $value['status'] === 200 &&
                $value['body'] === 'body content' &&
                $value['headers']['X-Foo'][0] === 'Bar';
        });

        $next = fn ($r, $o) => $response;

        $result = $middleware($request, [], $next);

        expect($result->getStatusCode())->toBe(200);
        expect((string) $result->getBody())->toBe('body content');
    });

    it('returns cached response if hit', function () {
        $cache = Mockery::mock(CacheInterface::class);
        $middleware = new CacheMiddleware($cache);

        $request = new Request('GET', 'https://example.com');

        $cachedValue = [
            'status' => 200,
            'headers' => ['X-Cached' => ['true']],
            'body' => 'cached body'
        ];

        $cache->shouldReceive('get')->once()->andReturn($cachedValue);

        // Next should NOT be called
        $next = fn ($r, $o) => throw new Exception('Should not be called');

        $result = $middleware($request, [], $next);

        expect($result->getStatusCode())->toBe(200);
        expect($result->getHeaderLine('X-Cached'))->toBe('true');
        expect((string) $result->getBody())->toBe('cached body');
    });

    it('does not cache non-GET requests', function () {
        $cache = Mockery::mock(CacheInterface::class);
        $middleware = new CacheMiddleware($cache);

        $request = new Request('POST', 'https://example.com');
        $response = new Response(201);

        $cache->shouldNotReceive('get');
        $cache->shouldNotReceive('set');

        $next = fn ($r, $o) => $response;

        $middleware($request, [], $next);
    });

    it('respects cache_ttl option', function () {
        $cache = Mockery::mock(CacheInterface::class);
        $middleware = new CacheMiddleware($cache, 3600);

        $request = new Request('GET', 'https://example.com');
        $response = new Response(200);

        $cache->shouldReceive('get')->andReturn(null);
        // Expect exact TTL 60
        $cache->shouldReceive('set')->with(Mockery::any(), Mockery::any(), 60);

        $next = fn ($r, $o) => $response;

        $result = $middleware($request, ['cache_ttl' => 60], $next);

        expect($result->getStatusCode())->toBe(200);
    });

    it('handles cache serialization failure (partial)', function () {
        // If cache.get throws or returns garbage that isn't an array (if implementation checks)
        $cache = Mockery::mock(CacheInterface::class);
        $middleware = new CacheMiddleware($cache);

        $request = new Request('GET', 'https://example.com');

        // If returns garbage but implementation checks is_array
        $cache->shouldReceive('get')->andReturn('garbage'); // Not null, but not array

        // It should proceed to next safely if code says `is_array`
        $response = new Response(200);
        $next = fn ($r, $o) => $response;

        // Expect set because it was a "miss" effectively
        $cache->shouldReceive('set');

        $result = $middleware($request, [], $next);

        expect($result->getStatusCode())->toBe(200);
    });
});
