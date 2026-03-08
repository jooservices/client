<?php

declare(strict_types=1);

namespace JOOservices\Client\Middleware;

use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\RejectedPromise;
use JOOservices\Client\Contracts\MiddlewareInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

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

        // Iterate in reverse order so first-added middleware becomes outermost
        // (Guzzle's push() adds to top of stack)
        foreach (array_reverse($this->order) as $name) {
            if (!isset($this->middlewares[$name])) {
                continue;
            }

            $middleware = $this->middlewares[$name];

            // Convert our MiddlewareInterface to Guzzle Middleware
            $guzzleMiddleware = function (callable $handler) use ($middleware) {
                return function (RequestInterface $request, array $options) use ($handler, $middleware) {
                    // Wrap handler to resolve promises (supports both sync and async)
                    $nextClosure = function (RequestInterface $req, array $opts) use ($handler): ResponseInterface {
                        $result = $handler($req, $opts);

                        if ($result instanceof \GuzzleHttp\Promise\PromiseInterface) {
                            $resolved = $result->wait();
                            if ($resolved instanceof ResponseInterface) {
                                return $resolved;
                            }

                            throw new RuntimeException('Middleware handler resolved to a non-response value.');
                        }

                        if ($result instanceof ResponseInterface) {
                            return $result;
                        }

                        throw new RuntimeException('Middleware handler returned an invalid response type.');
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
