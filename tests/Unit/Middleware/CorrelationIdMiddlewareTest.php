<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use JOOservices\Client\Middleware\CorrelationIdMiddleware;

describe('CorrelationIdMiddleware', function () {
    it('adds X-Correlation-ID header if missing', function () {
        $middleware = new CorrelationIdMiddleware();
        $request = new Request('GET', 'https://example.com/api');

        $next = function ($req, $opts) {
            // Verify header was added
            expect($req->hasHeader('X-Correlation-ID'))->toBeTrue();
            expect($req->getHeaderLine('X-Correlation-ID'))->not->toBeEmpty();

            return new Response(200);
        };

        $middleware($request, [], $next);
    });

    it('does not overwrite existing X-Correlation-ID header', function () {
        $middleware = new CorrelationIdMiddleware();
        $existingId = 'existing-correlation-id-123';
        $request = new Request('GET', 'https://example.com/api', [
            'X-Correlation-ID' => $existingId,
        ]);

        $next = function ($req, $opts) use ($existingId) {
            expect($req->getHeaderLine('X-Correlation-ID'))->toBe($existingId);
            return new Response(200);
        };

        $middleware($request, [], $next);
    });

    it('propagates correlation ID to response', function () {
        $middleware = new CorrelationIdMiddleware();
        $request = new Request('GET', 'https://example.com/api');

        $next = function ($req, $opts) {
            // Return response without the header
            return new Response(200);
        };

        $response = $middleware($request, [], $next);

        expect($response->hasHeader('X-Correlation-ID'))->toBeTrue();
    });

    it('uses custom header name from options', function () {
        $middleware = new CorrelationIdMiddleware();
        $request = new Request('GET', 'https://example.com/api');
        $customHeader = 'X-Request-ID';

        $next = function ($req, $opts) use ($customHeader) {
            expect($req->hasHeader($customHeader))->toBeTrue();
            return new Response(200);
        };

        $response = $middleware($request, ['correlation_header' => $customHeader], $next);

        expect($response->hasHeader($customHeader))->toBeTrue();
    });

    it('does not add header to response if already present', function () {
        $middleware = new CorrelationIdMiddleware();
        $responseId = 'response-correlation-id';
        $request = new Request('GET', 'https://example.com/api');

        $next = function ($req, $opts) use ($responseId) {
            return new Response(200, ['X-Correlation-ID' => $responseId]);
        };

        $response = $middleware($request, [], $next);

        // Should keep the response's original ID
        expect($response->getHeaderLine('X-Correlation-ID'))->toBe($responseId);
    });
});
