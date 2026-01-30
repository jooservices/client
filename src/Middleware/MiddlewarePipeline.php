<?php

declare(strict_types=1);

namespace JOOservices\Client\Middleware;

use Closure;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\RejectedPromise;
use JOOservices\Client\Contracts\MiddlewareInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class MiddlewarePipeline
{
    /**
     * @var array<string, MiddlewareInterface>
     */
    private array $middlewares = [];

    /**
     * @var array<string>
     */
    private array $order = [];

    /**
     * Add a middleware to the pipeline.
     *
     * @param MiddlewareInterface $middleware
     * @param string $name
     * @return self
     */
    public function push(MiddlewareInterface $middleware, string $name): self
    {
        $this->middlewares[$name] = $middleware;

        // Remove existing position if any (to move to end)
        $key = array_search($name, $this->order, true);
        if ($key !== false) {
            unset($this->order[$key]);
        }

        $this->order[] = $name;
        $this->order = array_values($this->order); // reindex

        return $this;
    }

    /**
     * Remove a middleware by name.
     */
    public function remove(string $name): self
    {
        unset($this->middlewares[$name]);

        $key = array_search($name, $this->order, true);
        if ($key !== false) {
            unset($this->order[$key]);
            $this->order = array_values($this->order);
        }

        return $this;
    }

    /**
     * Check if middleware exists.
     */
    public function has(string $name): bool
    {
        return isset($this->middlewares[$name]);
    }

    /**
     * Build the Guzzle HandlerStack.
     */
    public function buildHandlerStack(?HandlerStack $stack = null): HandlerStack
    {
        $stack = $stack ?? HandlerStack::create();

        // We iterate our order.
        // Guzzle Stack: push() adds to the top (executed first).
        // Our Pipeline: We usually want FIFO (First added = Outer most).
        // But traditionally in Middleware:
        // OUT -> Middleware 1 -> Middleware 2 -> Core -> Middleware 2 -> Middleware 1 -> OUT
        //
        // If we want $this->order[0] to be outer-most:
        // We need to push them onto the Guzzle stack ensuring $order[0] ends up on top.
        // Guzzle push() puts it on top.
        // So we should iterate $order in REVERSE.

        foreach (array_reverse($this->order) as $name) {
            if (!isset($this->middlewares[$name])) {
                continue;
            }

            $middleware = $this->middlewares[$name];

            // Convert our MiddlewareInterface to Guzzle Middleware
            $guzzleMiddleware = function (callable $handler) use ($middleware) {
                return function (RequestInterface $request, array $options) use ($handler, $middleware) {
                    // $handler is the 'next' closure in our world, but Guzzle expects promises.
                    // Wait, we designed our interface for synchronous ResponseInterface?
                    // "ResponseInterface".
                    // Guzzle 7 is Promise based.
                    // If we force synchronous return in our middleware, we break Guzzle async.
                    //
                    // Correction: Our Interface said "return ResponseInterface".
                    // If we want to support Guzzle async, we must return PromiseInterface|ResponseInterface.
                    // OR we force synchronous behavior (Block inside).
                    //
                    // Phase 1 Architecture Decision was "Hide Guzzle".
                    // But if we use Guzzle Adapter, we are bound to its async nature internally even if we expose sync.
                    //
                    // If we wrap Guzzle middleware:
                    // $next($request, $options) returns a Promise.
                    //
                    // Our MiddlewareInterface signature:
                    // __invoke(Request, Options, Next): ResponseInterface
                    //
                    // If we want compatible logic:
                    // We must resolve the promise from $next() inside our wrapper IF our user middleware
                    // expects strict ResponseInterface.
                    // But blocking breaks async pipeline if we ever expose it.
                    //
                    // Let's assume for Phase 2 we are OK blocking because our Public API `HttpClient`
                    // returns `ResponseWrapper` which wraps a `ResponseInterface` (already resolved).
                    // `GuzzleHttpClientAdapter::send()` calls `$client->send()` which is synchronous (blocks).
                    // So inside the stack, we are running in a blocking context mostly.
                    //
                    // ADAPTER implementation:
                    // $guzzleMiddleware function:

                    // $nextClosure = function (RequestInterface $req, array $opts) use ($handler): ResponseInterface {
                    //     $promise = $handler($req, $opts);

                    //     if ($promise instanceof \GuzzleHttp\Promise\PromiseInterface) {
                    //         /** @var ResponseInterface */
                    //         return $promise->wait();
                    //     }

                    //     /** @var ResponseInterface */
                    //     return $promise;
                    // };

                    $nextClosure = function (RequestInterface $req, array $opts) use ($handler): ResponseInterface {
                        /** @var \GuzzleHttp\Promise\PromiseInterface|ResponseInterface $result */
                        $result = $handler($req, $opts);

                        if ($result instanceof \GuzzleHttp\Promise\PromiseInterface) {
                            /** @var ResponseInterface */
                            return $result->wait();
                        }

                        return $result;
                    };



                    try {
                        $response = $middleware($request, $options, $nextClosure);
                        return new FulfilledPromise($response);
                    } catch (\Throwable $e) {
                        return new RejectedPromise($e);
                    }
                };
            };

            $stack->push($guzzleMiddleware, $name);
        }

        return $stack;
    }
}
