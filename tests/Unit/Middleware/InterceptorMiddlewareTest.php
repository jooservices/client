<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use JOOservices\Client\Middleware\InterceptorMiddleware;

describe('InterceptorMiddleware', function () {
    it('runs request interceptors', function () {
        $middleware = new InterceptorMiddleware();

        $middleware->onRequest(function ($request, $options) {
            return $request->withHeader('X-Interceptor', 'Run');
        });

        $request = new Request('GET', 'https://example.com');
        $response = new Response(200);

        $next = function ($req, $opts) use ($response) {
            expect($req->getHeaderLine('X-Interceptor'))->toBe('Run');
            return $response;
        };

        $middleware($request, [], $next);
    });

    it('runs response interceptors', function () {
        $middleware = new InterceptorMiddleware();

        $middleware->onResponse(function ($response, $options) {
            return $response->withHeader('X-Res-Interceptor', 'Run');
        });

        $request = new Request('GET', 'https://example.com');
        $response = new Response(200);

        $next = fn ($req, $opts) => $response;

        $result = $middleware($request, [], $next);

        expect($result->getHeaderLine('X-Res-Interceptor'))->toBe('Run');
    });

    it('chains interceptors', function () {
        $middleware = new InterceptorMiddleware();

        $middleware->onRequest(fn ($r) => $r->withHeader('X-1', 'A'));
        $middleware->onRequest(fn ($r) => $r->withHeader('X-2', 'B'));

        $request = new Request('GET', 'https://example.com');
        $response = new Response(200);

        $next = function ($req, $opts) use ($response) {
            expect($req->getHeaderLine('X-1'))->toBe('A');
            expect($req->getHeaderLine('X-2'))->toBe('B');
            return $response;
        };

        $middleware($request, [], $next);
    });
});
