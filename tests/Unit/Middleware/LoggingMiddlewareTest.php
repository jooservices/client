<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use JOOservices\Client\Client\HttpClient;
use JOOservices\Client\Contracts\WanIpProviderInterface;
use JOOservices\Client\Middleware\LoggingMiddleware;
use JOOservices\Client\Support\TransferStatsBag;
use Psr\Log\LoggerInterface;

describe('LoggingMiddleware', function () {
    it('logs request and response', function () {
        $logger = Mockery::mock(LoggerInterface::class);
        $middleware = new LoggingMiddleware($logger);

        $request = new Request('GET', 'https://example.com/api');
        $response = new Response(201);

        // Log request
        $logger->shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'Sending request to GET') &&
                    $context['uri'] === 'https://example.com/api';
            });

        // Log response
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
    });

    it('logs error level for 4xx/5xx responses', function () {
        $logger = Mockery::mock(LoggerInterface::class);
        $middleware = new LoggingMiddleware($logger);

        $request = new Request('GET', 'https://example.com');
        $response = new Response(500);

        $logger->shouldReceive('info'); // request log

        $logger->shouldReceive('log')
            ->once()
            ->withArgs(function ($level, $message, $context) {
                return $level === 'error' && $context['status'] === 500;
            });

        $next = fn ($r, $o) => $response;

        $middleware($request, [], $next);
    });

    it('logs exception', function () {
        $logger = Mockery::mock(LoggerInterface::class);
        $middleware = new LoggingMiddleware($logger);

        $request = new Request('GET', 'https://example.com');

        $logger->shouldReceive('info'); // request log

        $logger->shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'Exception for GET') &&
                    isset($context['exception']);
            });

        $next = fn ($r, $o) => throw new RuntimeException('Connection failed');

        expect(fn () => $middleware($request, [], $next))->toThrow(RuntimeException::class);
    });

    it('logs bodies when enabled', function () {
        $logger = Mockery::mock(LoggerInterface::class);
        $middleware = new LoggingMiddleware($logger, logBodies: true);

        $request = new Request('POST', 'https://example.com', [], 'request body');
        $response = new Response(200, [], 'response body');

        $logger->shouldReceive('info'); // req line

        // Log Request Body
        $logger->shouldReceive('debug')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'Request Body' && $context['body'] === 'request body';
            });

        $logger->shouldReceive('log'); // res line

        // Log Response Body
        $logger->shouldReceive('debug')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'Response Body' && $context['body'] === 'response body';
            });

        $next = fn ($r, $o) => $response;

        $middleware($request, [], $next);

        // Ensure streams are rewound if possible (checked implicitly if logic runs without crashing)
        // Check if body is still readable?
        expect((string) $request->getBody())->toBe('request body');
    });

    it('adds local, wan, target ip metadata to response context', function () {
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
    });
});
