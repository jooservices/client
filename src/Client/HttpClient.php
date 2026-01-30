<?php

declare(strict_types=1);

namespace JOOservices\Client\Client;

use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Utils;
use JOOservices\Client\Contracts\AsyncHttpClientInterface;
use JOOservices\Client\Contracts\HttpClientInterface;
use JOOservices\Client\Contracts\ResponseWrapperInterface;
use JOOservices\Client\Contracts\TransportAdapterInterface;
use JOOservices\Client\Response\ResponseWrapper;
use JOOservices\Client\Support\OptionsMerger;
use JOOservices\Client\ValueObjects\ClientConfig;
use Psr\Http\Message\RequestInterface;

final readonly class HttpClient implements HttpClientInterface, AsyncHttpClientInterface
{
    private OptionsMerger $merger;

    public function __construct(
        private TransportAdapterInterface $adapter,
        private ClientConfig $config,
        ?OptionsMerger $merger = null
    ) {
        $this->merger = $merger ?? new OptionsMerger();
    }

    public function get(string $uri, array $options = []): ResponseWrapperInterface
    {
        return $this->request('GET', $uri, $options);
    }

    public function post(string $uri, array $options = []): ResponseWrapperInterface
    {
        return $this->request('POST', $uri, $options);
    }

    public function put(string $uri, array $options = []): ResponseWrapperInterface
    {
        return $this->request('PUT', $uri, $options);
    }

    public function patch(string $uri, array $options = []): ResponseWrapperInterface
    {
        return $this->request('PATCH', $uri, $options);
    }

    public function delete(string $uri, array $options = []): ResponseWrapperInterface
    {
        return $this->request('DELETE', $uri, $options);
    }

    public function request(string $method, string $uri, array $options = []): ResponseWrapperInterface
    {
        $globalOptions = $this->config->toGuzzleOptions();
        $finalOptions = $this->merger->merge($globalOptions, $options);
        $request = new Request($method, $uri);
        $psrResponse = $this->adapter->send($request, $finalOptions);
        return new ResponseWrapper($psrResponse);
    }

    public function getAsync(string $uri, array $options = []): PromiseInterface
    {
        return $this->requestAsync('GET', $uri, $options);
    }

    public function postAsync(string $uri, array $options = []): PromiseInterface
    {
        return $this->requestAsync('POST', $uri, $options);
    }

    public function requestAsync(string $method, string $uri, array $options = []): PromiseInterface
    {
        $globalOptions = $this->config->toGuzzleOptions();
        $finalOptions = $this->merger->merge($globalOptions, $options);
        $request = new Request($method, $uri);

        return $this->adapter->sendAsync($request, $finalOptions)
            ->then(function ($response) {
                return new ResponseWrapper($response);
            });
    }

    public function batch(iterable $requests, int $concurrency = 25): array
    {
        $results = [];

        $generator = function () use ($requests) {
            foreach ($requests as $key => $r) {
                $promise = null;
                if ($r instanceof PromiseInterface) {
                    $promise = $r;
                } elseif ($r instanceof RequestInterface) {
                    $options = [];
                    if ($headers = $r->getHeaders()) {
                        $options['headers'] = $headers;
                    }
                    $body = (string) $r->getBody();
                    if ($body !== '') {
                        $options['body'] = $body;
                    }
                    $promise = $this->requestAsync($r->getMethod(), (string) $r->getUri(), $options);
                } elseif (is_callable($r)) {
                    $promise = $r();
                }

                if ($promise) {
                    // Wrap to preserve key and handle success/failure
                    yield $key => $promise->then(
                        function ($value) use ($key) {
                            return ['key' => $key, 'value' => $value, 'state' => 'fulfilled'];
                        },
                        function ($reason) use ($key) {
                            return ['key' => $key, 'value' => $reason, 'state' => 'rejected'];
                        }
                    );
                }
            }
        };

        $promise = \GuzzleHttp\Promise\Each::ofLimit(
            $generator(),
            $concurrency,
            function ($wrapped, $idx) use (&$results) {
                if (isset($wrapped['key'])) {
                    $results[$wrapped['key']] = $wrapped['value'];
                }
            },
            function ($reason, $idx) use (&$results) {
                // Should not happen as we catch rejections in the wrapper.
            }
        );

        $promise->wait();

        return $results;
    }
}
