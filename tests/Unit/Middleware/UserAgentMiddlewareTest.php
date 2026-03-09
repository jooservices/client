<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use JOOservices\Client\Middleware\UserAgentMiddleware;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('unit')]
class UserAgentMiddlewareTest extends TestCase
{
    public function test_adds_user_agent_if_missing(): void
    {
        $middleware = new UserAgentMiddleware('MyAgent/1.0');
        $request = new Request('GET', 'https://example.com');

        $next = function ($req, $opts) {
            $this->assertSame('MyAgent/1.0', $req->getHeaderLine('User-Agent'));
            return new Response(200);
        };

        $middleware($request, [], $next);
    }

    public function test_preserves_existing_user_agent(): void
    {
        $middleware = new UserAgentMiddleware('MyAgent/1.0');
        $request = new Request('GET', 'https://example.com', ['User-Agent' => 'Existing/1.0']);

        $next = function ($req, $opts) {
            $this->assertSame('Existing/1.0', $req->getHeaderLine('User-Agent'));
            return new Response(200);
        };

        $middleware($request, [], $next);
    }

    public function test_replaces_empty_user_agent(): void
    {
        $middleware = new UserAgentMiddleware('MyAgent/1.0');
        $request = new Request('GET', 'https://example.com', ['User-Agent' => '   ']);

        $next = function ($req, $opts) {
            $this->assertSame('MyAgent/1.0', $req->getHeaderLine('User-Agent'));
            return new Response(200);
        };

        $middleware($request, [], $next);
    }
}
