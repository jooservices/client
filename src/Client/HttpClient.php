<?php

declare(strict_types=1);

namespace JOOservices\Client\Client;

use JOOservices\Client\Contracts\TransportInterface;
use JOOservices\Client\Dto\ClientConfig;
use JOOservices\Client\Dto\RequestOptions;
use JOOservices\Client\Exceptions\RequestException;
use JOOservices\Client\Middleware\MiddlewarePipeline;
use JOOservices\Client\Request\RequestBuilder;
use JOOservices\Client\Support\StreamContents;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class HttpClient implements ClientInterface
{
    public function __construct(
        private readonly TransportInterface $transport,
        private readonly MiddlewarePipeline $pipeline,
        private readonly ClientConfig $config,
        private readonly HttpClientSupport $support,
    ) {
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        return $this->dispatch($request, new RequestOptions());
    }

    /** @param array<string, mixed>|RequestOptions $options */
    public function send(RequestInterface $request, array|RequestOptions $options = []): ResponseInterface
    {
        $delta = $options instanceof RequestOptions ? $options : $this->support->options->fromArray($options);

        return $this->dispatch($request, $delta);
    }

    public function requestBuilder(): RequestBuilder
    {
        return new RequestBuilder($this->support->requests, $this->support->streams, $this->support->uris);
    }

    private function dispatch(RequestInterface $request, RequestOptions $delta): ResponseInterface
    {
        $this->support->options->assertTimeouts($delta);
        $this->support->headers->assertRequest($request);
        $this->transport->capabilities()->assertHonors($delta);
        $prepared = $this->applyBaseUri($this->applyDefaultHeaders($this->ensureSeekableBody($request)));
        $this->support->headers->assertRequest($prepared);

        return $this->pipeline->handle($prepared, $this->support->options->merge($delta, $this->config));
    }

    /**
     * Buffer a non-seekable body into a seekable one before it ever reaches the middleware pipeline.
     * Signing, caching, and failover retries all need to read the body more than once (or after another
     * middleware already consumed it); a stream that can't rewind gets permanently drained the first
     * time any of them reads it, silently sending an empty body downstream. Doing this once here, at
     * the earliest chokepoint, means no individual middleware has to special-case it.
     */
    private function ensureSeekableBody(RequestInterface $request): RequestInterface
    {
        $body = $request->getBody();
        if ($body->isSeekable()) {
            return $request;
        }

        $resource = (new StreamContents())->copyToResource($body);

        return $request->withBody($this->support->streams->createStreamFromResource($resource));
    }

    private function applyDefaultHeaders(RequestInterface $request): RequestInterface
    {
        foreach ($this->config->headers as $name => $value) {
            if (! $request->hasHeader($name)) {
                /** @var string|list<string> $value */
                $request = $request->withHeader($name, $value);
            }
        }

        return $request;
    }

    private function applyBaseUri(RequestInterface $request): RequestInterface
    {
        $uri = $request->getUri();
        if ($uri->getScheme() !== '' && $uri->getHost() !== '') {
            return $request;
        }

        if ($uri->getHost() !== '' && $uri->getScheme() === '') {
            throw new RequestException($request, 'Protocol-relative URIs are not resolved against the base URI.');
        }

        $baseUri = $this->support->baseUris->normalize($this->config->baseUri);
        if ($baseUri === '') {
            throw new RequestException($request, 'A relative URI requires a configured base URI.');
        }

        $resolved = $this->support->resolver->resolve($this->support->uris->createUri($baseUri), $uri);
        if ($resolved->getScheme() === '' || $resolved->getHost() === '') {
            throw new RequestException($request, 'The request URI could not be resolved to an absolute HTTP URI.');
        }

        return $request->withUri($resolved, false);
    }
}
