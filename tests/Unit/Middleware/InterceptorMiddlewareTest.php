<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use JOOservices\Client\Middleware\InterceptorMiddleware;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('unit')]
class InterceptorMiddlewareTest extends TestCase
{
    public function test_runs_request_interceptors(): void
    {
        $middleware = new InterceptorMiddleware();

        $middleware->onRequest(function ($request, $options) {
            return $request->withHeader('X-Interceptor', 'Run');
        });

        $request = new Request('GET', 'https://example.com');
        $response = new Response(200);

        $next = function ($req, $opts) use ($response) {
            $this->assertSame('Run', $req->getHeaderLine('X-Interceptor'));
            return $response;
        };

        $middleware($request, [], $next);
    }

    public function test_runs_response_interceptors(): void
    {
        $middleware = new InterceptorMiddleware();

        $middleware->onResponse(function ($response, $options) {
            return $response->withHeader('X-Res-Interceptor', 'Run');
        });

        $request = new Request('GET', 'https://example.com');
        $response = new Response(200);

        $next = fn ($req, $opts) => $response;

        $result = $middleware($request, [], $next);

        $this->assertSame('Run', $result->getHeaderLine('X-Res-Interceptor'));
    }

    public function test_chains_interceptors(): void
    {
        $middleware = new InterceptorMiddleware();

        $middleware->onRequest(fn ($r) => $r->withHeader('X-1', 'A'));
        $middleware->onRequest(fn ($r) => $r->withHeader('X-2', 'B'));

        $request = new Request('GET', 'https://example.com');
        $response = new Response(200);

        $next = function ($req, $opts) use ($response) {
            $this->assertSame('A', $req->getHeaderLine('X-1'));
            $this->assertSame('B', $req->getHeaderLine('X-2'));
            return $response;
        };

        $middleware($request, [], $next);
    }
}
