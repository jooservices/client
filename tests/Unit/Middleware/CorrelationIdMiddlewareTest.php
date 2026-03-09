<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use JOOservices\Client\Middleware\CorrelationIdMiddleware;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('unit')]
class CorrelationIdMiddlewareTest extends TestCase
{
    public function test_adds_X_Correlation_ID_header_if_missing(): void
    {
        $middleware = new CorrelationIdMiddleware();
        $request = new Request('GET', 'https://example.com/api');

        $next = function ($req, $opts) {
            $this->assertTrue($req->hasHeader('X-Correlation-ID'));
            $this->assertNotEmpty($req->getHeaderLine('X-Correlation-ID'));

            return new Response(200);
        };

        $middleware($request, [], $next);
    }

    public function test_does_not_overwrite_existing_X_Correlation_ID_header(): void
    {
        $middleware = new CorrelationIdMiddleware();
        $existingId = 'existing-correlation-id-123';
        $request = new Request('GET', 'https://example.com/api', [
            'X-Correlation-ID' => $existingId,
        ]);

        $next = function ($req, $opts) use ($existingId) {
            $this->assertSame($existingId, $req->getHeaderLine('X-Correlation-ID'));
            return new Response(200);
        };

        $middleware($request, [], $next);
    }

    public function test_propagates_correlation_ID_to_response(): void
    {
        $middleware = new CorrelationIdMiddleware();
        $request = new Request('GET', 'https://example.com/api');

        $next = function ($req, $opts) {
            return new Response(200);
        };

        $response = $middleware($request, [], $next);

        $this->assertTrue($response->hasHeader('X-Correlation-ID'));
    }

    public function test_uses_custom_header_name_from_options(): void
    {
        $middleware = new CorrelationIdMiddleware();
        $request = new Request('GET', 'https://example.com/api');
        $customHeader = 'X-Request-ID';

        $next = function ($req, $opts) use ($customHeader) {
            $this->assertTrue($req->hasHeader($customHeader));
            return new Response(200);
        };

        $response = $middleware($request, ['correlation_header' => $customHeader], $next);

        $this->assertTrue($response->hasHeader($customHeader));
    }

    public function test_does_not_add_header_to_response_if_already_present(): void
    {
        $middleware = new CorrelationIdMiddleware();
        $responseId = 'response-correlation-id';
        $request = new Request('GET', 'https://example.com/api');

        $next = function ($req, $opts) use ($responseId) {
            return new Response(200, ['X-Correlation-ID' => $responseId]);
        };

        $response = $middleware($request, [], $next);

        $this->assertSame($responseId, $response->getHeaderLine('X-Correlation-ID'));
    }
}
