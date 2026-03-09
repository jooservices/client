<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use Closure;
use JOOservices\Client\Contracts\MiddlewareInterface;
use JOOservices\Client\Middleware\MiddlewarePipeline;
use PHPUnit\Framework\Attributes\Group;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Tests\TestCase;

#[Group('unit')]
class MiddlewarePipelineTest extends TestCase
{
    public function test_pipeline_manages_middleware_order_and_execution(): void
    {
        $pipeline = new MiddlewarePipeline();
        $executionOrder = [];

        $m1 = new class ($executionOrder, 'm1') implements MiddlewareInterface {
            public function __construct(private array &$log, private string $name)
            {
            }
            public function __invoke(RequestInterface $request, array $options, Closure $next): ResponseInterface
            {
                $this->log[] = $this->name . '_req';
                $response = $next($request, $options);
                $this->log[] = $this->name . '_res';
                return $response;
            }
        };

        $m2 = new class ($executionOrder, 'm2') implements MiddlewareInterface {
            public function __construct(private array &$log, private string $name)
            {
            }
            public function __invoke(RequestInterface $request, array $options, Closure $next): ResponseInterface
            {
                $this->log[] = $this->name . '_req';
                $response = $next($request, $options);
                $this->log[] = $this->name . '_res';
                return $response;
            }
        };

        $pipeline->push($m1, 'm1');
        $pipeline->push($m2, 'm2');

        $stack = $pipeline->buildHandlerStack();

        $stack->setHandler(function ($request, $options) use (&$executionOrder) {
            $executionOrder[] = 'handler';
            return \GuzzleHttp\Promise\Create::promiseFor(new \GuzzleHttp\Psr7\Response(200));
        });

        $stack->resolve()(new \GuzzleHttp\Psr7\Request('GET', '/'), [])->wait();

        // Pipeline runs last-pushed first (LIFO): m2 wraps m1 wraps handler
        $this->assertSame(
            ['m2_req', 'm1_req', 'handler', 'm1_res', 'm2_res'],
            $executionOrder
        );
    }
}
