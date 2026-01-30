<?php

declare(strict_types=1);

namespace JOOservices\Client\Contracts;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

interface TransportAdapterInterface
{
    /**
     * Send a PSR-7 request and return a PSR-7 response.
     *
     * @param  array<string, mixed>  $options
     */
    public function send(RequestInterface $request, array $options = []): ResponseInterface;

    /**
     * Send an asynchronous HTTP request.
     *
     * @param  array<string, mixed>  $options
     */
    public function sendAsync(RequestInterface $request, array $options = []): \GuzzleHttp\Promise\PromiseInterface;
}
