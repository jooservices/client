<?php

declare(strict_types=1);

namespace JOOservices\Client\Contracts;

interface HttpClientInterface
{
    /**
     * Send a GET request.
     *
     * @param  array<string, mixed>  $options
     */
    public function get(string $uri, array $options = []): ResponseWrapperInterface;

    /**
     * Send a POST request.
     *
     * @param  array<string, mixed>  $options
     */
    public function post(string $uri, array $options = []): ResponseWrapperInterface;

    /**
     * Send a PUT request.
     *
     * @param  array<string, mixed>  $options
     */
    public function put(string $uri, array $options = []): ResponseWrapperInterface;

    /**
     * Send a PATCH request.
     *
     * @param  array<string, mixed>  $options
     */
    public function patch(string $uri, array $options = []): ResponseWrapperInterface;

    /**
     * Send a DELETE request.
     *
     * @param  array<string, mixed>  $options
     */
    public function delete(string $uri, array $options = []): ResponseWrapperInterface;

    /**
     * Send a general request.
     *
     * @param  array<string, mixed>  $options
     */
    public function request(string $method, string $uri, array $options = []): ResponseWrapperInterface;
}
