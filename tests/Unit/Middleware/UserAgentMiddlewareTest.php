<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use JOOservices\Client\Middleware\UserAgentMiddleware;

describe('UserAgentMiddleware', function () {
    it('adds user agent if missing', function () {
        $middleware = new UserAgentMiddleware('MyAgent/1.0');
        $request = new Request('GET', 'https://example.com');

        $next = function ($req, $opts) {
            expect($req->getHeaderLine('User-Agent'))->toBe('MyAgent/1.0');
            return new Response(200);
        };

        $middleware($request, [], $next);
    });

    it('preserves existing user agent', function () {
        $middleware = new UserAgentMiddleware('MyAgent/1.0');
        $request = new Request('GET', 'https://example.com', ['User-Agent' => 'Existing/1.0']);

        $next = function ($req, $opts) {
            expect($req->getHeaderLine('User-Agent'))->toBe('Existing/1.0');
            return new Response(200);
        };

        $middleware($request, [], $next);
    });

    it('replaces empty user agent', function () {
        $middleware = new UserAgentMiddleware('MyAgent/1.0');
        // ' ' is effectively empty or missing for some? Logic says trim() === ''
        $request = new Request('GET', 'https://example.com', ['User-Agent' => '   ']);

        $next = function ($req, $opts) {
            expect($req->getHeaderLine('User-Agent'))->toBe('MyAgent/1.0');
            return new Response(200);
        };

        $middleware($request, [], $next);
    });
});
