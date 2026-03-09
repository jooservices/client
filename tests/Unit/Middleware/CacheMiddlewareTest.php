<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use Exception;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use JOOservices\Client\Middleware\CacheMiddleware;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use Psr\SimpleCache\CacheInterface;
use Tests\TestCase;

#[Group('unit')]
class CacheMiddlewareTest extends TestCase
{
    public function test_caches_GET_requests(): void
    {
        $cache = Mockery::mock(CacheInterface::class);
        $middleware = new CacheMiddleware($cache);

        $request = new Request('GET', 'https://example.com');
        $response = new Response(200, ['X-Foo' => 'Bar'], 'body content');

        $cache->shouldReceive('get')->once()->andReturn(null);

        $cache->shouldReceive('set')->once()->withArgs(function ($key, $value, $ttl) {
            return str_contains($key, 'http_cache_') &&
                $value['status'] === 200 &&
                $value['body'] === 'body content' &&
                $value['headers']['X-Foo'][0] === 'Bar';
        });

        $next = fn ($r, $o) => $response;

        $result = $middleware($request, [], $next);

        $this->assertSame(200, $result->getStatusCode());
        $this->assertSame('body content', (string) $result->getBody());
    }

    public function test_returns_cached_response_if_hit(): void
    {
        $cache = Mockery::mock(CacheInterface::class);
        $middleware = new CacheMiddleware($cache);

        $request = new Request('GET', 'https://example.com');

        $cachedValue = [
            'status' => 200,
            'headers' => ['X-Cached' => ['true']],
            'body' => 'cached body'
        ];

        $cache->shouldReceive('get')->once()->andReturn($cachedValue);

        $next = fn ($r, $o) => throw new Exception('Should not be called');

        $result = $middleware($request, [], $next);

        $this->assertSame(200, $result->getStatusCode());
        $this->assertSame('true', $result->getHeaderLine('X-Cached'));
        $this->assertSame('cached body', (string) $result->getBody());
    }

    public function test_does_not_cache_non_GET_requests(): void
    {
        $cache = Mockery::mock(CacheInterface::class);
        $middleware = new CacheMiddleware($cache);

        $request = new Request('POST', 'https://example.com');
        $response = new Response(201);

        $cache->shouldNotReceive('get');
        $cache->shouldNotReceive('set');

        $next = fn ($r, $o) => $response;

        $middleware($request, [], $next);
        $this->addToAssertionCount(1);
    }

    public function test_respects_cache_ttl_option(): void
    {
        $cache = Mockery::mock(CacheInterface::class);
        $middleware = new CacheMiddleware($cache, 3600);

        $request = new Request('GET', 'https://example.com');
        $response = new Response(200);

        $cache->shouldReceive('get')->andReturn(null);
        $cache->shouldReceive('set')->with(Mockery::any(), Mockery::any(), 60);

        $next = fn ($r, $o) => $response;

        $result = $middleware($request, ['cache_ttl' => 60], $next);

        $this->assertSame(200, $result->getStatusCode());
    }

    public function test_handles_cache_serialization_failure_partial(): void
    {
        $cache = Mockery::mock(CacheInterface::class);
        $middleware = new CacheMiddleware($cache);

        $request = new Request('GET', 'https://example.com');

        $cache->shouldReceive('get')->andReturn('garbage');

        $response = new Response(200);
        $next = fn ($r, $o) => $response;

        $cache->shouldReceive('set');

        $result = $middleware($request, [], $next);

        $this->assertSame(200, $result->getStatusCode());
    }
}
