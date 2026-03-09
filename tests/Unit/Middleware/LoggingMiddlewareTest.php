<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use JOOservices\Client\Client\HttpClient;
use JOOservices\Client\Contracts\WanIpProviderInterface;
use JOOservices\Client\Middleware\LoggingMiddleware;
use JOOservices\Client\Support\TransferStatsBag;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

#[Group('unit')]
class LoggingMiddlewareTest extends TestCase
{
    public function test_logs_request_and_response(): void
    {
        $logger = Mockery::mock(LoggerInterface::class);
        $middleware = new LoggingMiddleware($logger);

        $request = new Request('GET', 'https://example.com/api');
        $response = new Response(201);

        $logger->shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'Sending request to GET') &&
                    $context['uri'] === 'https://example.com/api';
            });

        $logger->shouldReceive('log')
            ->once()
            ->withArgs(function ($level, $message, $context) {
                return $level === 'info' &&
                    str_contains($message, 'Received response 201') &&
                    $context['status'] === 201 &&
                    isset($context['duration_ms']);
            });

        $next = fn ($r, $o) => $response;

        $middleware($request, [], $next);
        $this->addToAssertionCount(1);
    }

    public function test_logs_error_level_for_4xx_5xx_responses(): void
    {
        $logger = Mockery::mock(LoggerInterface::class);
        $middleware = new LoggingMiddleware($logger);

        $request = new Request('GET', 'https://example.com');
        $response = new Response(500);

        $logger->shouldReceive('info');

        $logger->shouldReceive('log')
            ->once()
            ->withArgs(function ($level, $message, $context) {
                return $level === 'error' && $context['status'] === 500;
            });

        $next = fn ($r, $o) => $response;

        $middleware($request, [], $next);
        $this->addToAssertionCount(1);
    }

    public function test_logs_exception(): void
    {
        $logger = Mockery::mock(LoggerInterface::class);
        $middleware = new LoggingMiddleware($logger);

        $request = new Request('GET', 'https://example.com');

        $logger->shouldReceive('info');

        $logger->shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'Exception for GET') &&
                    isset($context['exception']);
            });

        $next = fn ($r, $o) => throw new \RuntimeException('Connection failed');

        $this->expectException(\RuntimeException::class);
        $middleware($request, [], $next);
    }

    public function test_logs_bodies_when_enabled(): void
    {
        $logger = Mockery::mock(LoggerInterface::class);
        $middleware = new LoggingMiddleware($logger, logBodies: true);

        $request = new Request('POST', 'https://example.com', [], 'request body');
        $response = new Response(200, [], 'response body');

        $logger->shouldReceive('info');

        $logger->shouldReceive('debug')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'Request Body' && $context['body'] === 'request body';
            });

        $logger->shouldReceive('log');

        $logger->shouldReceive('debug')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'Response Body' && $context['body'] === 'response body';
            });

        $next = fn ($r, $o) => $response;

        $middleware($request, [], $next);

        $this->assertSame('request body', (string) $request->getBody());
    }

    public function test_adds_local_wan_target_ip_metadata_to_response_context(): void
    {
        $logger = Mockery::mock(LoggerInterface::class);
        $wanProvider = new class () implements WanIpProviderInterface {
            public function getPublicIp(): ?string
            {
                return '198.51.100.25';
            }
        };

        $middleware = new LoggingMiddleware($logger, false, $wanProvider);
        $request = new Request('GET', 'https://example.com/path');
        $response = new Response(200);

        $logger->shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) {
                return isset($context['wan_ip'])
                    && $context['wan_ip'] === '198.51.100.25'
                    && $context['target_hostname'] === 'example.com';
            });

        $logger->shouldReceive('log')
            ->once()
            ->withArgs(function ($level, $message, $context) {
                return $level === 'info'
                    && $context['target_ip'] === '203.0.113.99'
                    && $context['local_ip'] === '10.0.0.21';
            });

        $statsBag = new TransferStatsBag();
        $options = [HttpClient::TRANSFER_STATS_OPTION_KEY => $statsBag];

        $next = function ($r, $o) use ($response, $statsBag) {
            $statsBag->targetIp = '203.0.113.99';
            $statsBag->localIp = '10.0.0.21';

            return $response;
        };

        $middleware($request, $options, $next);
        $this->addToAssertionCount(1);
    }
}
