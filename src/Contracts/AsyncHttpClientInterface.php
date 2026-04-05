<?php

declare(strict_types=1);

namespace JOOservices\Client\Contracts;

use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;

interface AsyncHttpClientInterface
{
    /**
     * Send an asynchronous request.
     *
     * @param string $method
     * @param string $uri
     * @param array<string, mixed> $options
     */
    public function requestAsync(string $method, string $uri, array $options = []): PromiseInterface;

    /**
     * Send an asynchronous GET request.
     *
     * @param string $uri
     * @param array<string, mixed> $options
     */
    public function getAsync(string $uri, array $options = []): PromiseInterface;

    /**
     * Send an asynchronous POST request.
     *
     * @param string $uri
     * @param array<string, mixed> $options
     */
    public function postAsync(string $uri, array $options = []): PromiseInterface;

    /**
     * Execute multiple requests concurrently.
     *
     * @param iterable<array-key, PromiseInterface|RequestInterface|callable(): PromiseInterface> $requests
     * @param int $concurrency Maximum number of concurrent requests.
     * @return array<array-key, mixed> Array of results keyed by the input keys.
     */
    public function batch(iterable $requests, int $concurrency = 25): array;
}
