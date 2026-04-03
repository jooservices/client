<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Uri;
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

    public function test_resolves_target_hostname_from_base_uri_when_request_uri_is_relative(): void
    {
        $logger = Mockery::mock(LoggerInterface::class);
        $middleware = new LoggingMiddleware($logger);

        $request = new Request('GET', '/api/path');
        $response = new Response(200);
        $options = ['base_uri' => 'https://api.example.com'];

        $logger->shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) {
                return $context['target_hostname'] === 'api.example.com';
            });
        $logger->shouldReceive('log')->once();

        $next = fn ($r, $o) => $response;
        $middleware($request, $options, $next);
        $this->addToAssertionCount(1);
    }

    public function test_resolves_target_hostname_from_base_uri_uri_interface(): void
    {
        $logger = Mockery::mock(LoggerInterface::class);
        $middleware = new LoggingMiddleware($logger);

        $request = new Request('GET', '/path');
        $response = new Response(200);
        $options = ['base_uri' => new Uri('https://service.example.com')];

        $logger->shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) {
                return $context['target_hostname'] === 'service.example.com';
            });
        $logger->shouldReceive('log')->once();

        $next = fn ($r, $o) => $response;
        $middleware($request, $options, $next);
        $this->addToAssertionCount(1);
    }

    public function test_resolves_target_hostname_from_effective_uri_on_response_when_request_uri_relative(): void
    {
        $logger = Mockery::mock(LoggerInterface::class);
        $middleware = new LoggingMiddleware($logger);

        $request = new Request('GET', '/relative');
        $response = new Response(200);
        $statsBag = new TransferStatsBag();
        $statsBag->effectiveUri = 'https://resolved.example.com/relative';
        $statsBag->targetIp = '93.184.0.1';
        $options = [HttpClient::TRANSFER_STATS_OPTION_KEY => $statsBag];

        $logger->shouldReceive('info')->once();
        $logger->shouldReceive('log')
            ->once()
            ->withArgs(function ($level, $message, $context) {
                return $context['target_hostname'] === 'resolved.example.com'
                    && $context['target_ip'] === '93.184.0.1';
            });

        $next = fn ($r, $o) => $response;
        $middleware($request, $options, $next);
        $this->addToAssertionCount(1);
    }

    public function test_resolves_target_hostname_from_effective_uri_on_exception(): void
    {
        $logger = Mockery::mock(LoggerInterface::class);
        $middleware = new LoggingMiddleware($logger);

        $request = new Request('GET', '/path');
        $statsBag = new TransferStatsBag();
        $statsBag->effectiveUri = 'https://failed.example.com/path';
        $options = [HttpClient::TRANSFER_STATS_OPTION_KEY => $statsBag];

        $logger->shouldReceive('info')->once();
        $logger->shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) {
                return $context['target_hostname'] === 'failed.example.com';
            });

        $next = fn ($r, $o) => throw new \RuntimeException('Connection refused');
        $this->expectException(\RuntimeException::class);
        $middleware($request, $options, $next);
    }

    public function test_logs_null_target_hostname_and_ignores_invalid_transfer_stats(): void
    {
        $logger = Mockery::mock(LoggerInterface::class);
        $middleware = new LoggingMiddleware($logger);

        $request = new Request('GET', '/relative-path');
        $response = new Response(200);
        $options = [HttpClient::TRANSFER_STATS_OPTION_KEY => new \stdClass()];

        $logger->shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) {
                return $context['target_hostname'] === null
                    && $context['target_ip'] === null
                    && $context['local_ip'] === null;
            });

        $logger->shouldReceive('log')
            ->once()
            ->withArgs(function ($level, $message, $context) {
                return $level === 'info'
                    && $context['target_hostname'] === null
                    && $context['target_ip'] === null
                    && $context['local_ip'] === null;
            });

        $next = fn ($r, $o) => $response;
        $middleware($request, $options, $next);
        $this->addToAssertionCount(1);
    }

    public function test_resolves_target_hostname_in_catch_when_effective_uri_arrives_late(): void
    {
        $logger = Mockery::mock(LoggerInterface::class);
        $middleware = new LoggingMiddleware($logger);

        $request = new Request('GET', '/late-failure');
        $statsBag = new TransferStatsBag();
        $options = [HttpClient::TRANSFER_STATS_OPTION_KEY => $statsBag];

        $logger->shouldReceive('info')->once();
        $logger->shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'Exception for GET /late-failure')
                    && $context['target_hostname'] === 'late.example.com';
            });

        $next = function ($r, $o) use ($statsBag) {
            $statsBag->effectiveUri = 'https://late.example.com/late-failure';
            throw new \RuntimeException('Late failure');
        };

        $this->expectException(\RuntimeException::class);
        $middleware($request, $options, $next);
    }
}
