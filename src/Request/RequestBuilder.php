<?php

declare(strict_types=1);

namespace JOOservices\Client\Request;

use JOOservices\Client\Dto\RequestOptions;
use JOOservices\Client\Exceptions\InvalidConfigurationException;
use JOOservices\Client\Support\HeaderValidator;
use JsonException;
use JsonSerializable;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriFactoryInterface;

final class RequestBuilder
{
    private string $method = 'GET';

    private string $uri = '';

    /** @var array<string, string|list<string>> */
    private array $headers = [];

    private string|StreamInterface $body = '';

    private RequestOptions $options;

    private ?PreparedRequest $built = null;

    public function __construct(
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly UriFactoryInterface $uriFactory,
        private readonly HeaderValidator $headerValidator = new HeaderValidator(),
    ) {
        $this->options = new RequestOptions();
    }

    public static function create(RequestFactoryInterface $requestFactory, StreamFactoryInterface $streamFactory, UriFactoryInterface $uriFactory): self
    {
        return new self($requestFactory, $streamFactory, $uriFactory);
    }

    public function get(string $uri): self
    {
        return $this->method('GET', $uri);
    }
    public function post(string $uri): self
    {
        return $this->method('POST', $uri);
    }
    public function put(string $uri): self
    {
        return $this->method('PUT', $uri);
    }
    public function patch(string $uri): self
    {
        return $this->method('PATCH', $uri);
    }
    public function delete(string $uri): self
    {
        return $this->method('DELETE', $uri);
    }
    public function head(string $uri): self
    {
        return $this->method('HEAD', $uri);
    }
    public function method(string $method, string $uri): self
    {
        $copy = clone $this;
        $copy->method = strtoupper($method);
        $copy->uri = $uri;
        return $copy;
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headerValidator->assertPair($name, $value);
        $copy = clone $this;
        $copy->headers[$name] = $value;
        return $copy;
    }

    /** @param array<string, string|list<string>> $headers */
    public function withHeaders(array $headers): self
    {
        $copy = clone $this;
        foreach ($headers as $name => $value) {
            if (is_array($value)) {
                foreach ($value as $item) {
                    $copy->headerValidator->assertPair($name, $item);
                    $existing = $copy->headers[$name] ?? [];
                    $copy->headers[$name] = is_array($existing) ? [...$existing, $item] : [$existing, $item];
                }

                continue;
            }

            $copy->headerValidator->assertPair($name, $value);
            $copy->headers[$name] = $value;
        }
        return $copy;
    }

    /** @param array<string, scalar|array<array-key, scalar>|null> $query */
    public function withQuery(array $query): self
    {
        $copy = clone $this;
        $uri = $this->uriFactory->createUri($copy->uri);
        $existing = $this->parseQueryString($uri->getQuery());
        $copy->uri = (string) $uri->withQuery(http_build_query(array_replace($existing, $query), '', '&', PHP_QUERY_RFC3986));
        return $copy;
    }

    /**
     * parse_str() is unsuitable here: it silently rewrites "." and " " in key names to "_" (a PHP
     * variable-name-safety quirk from its $_GET/$_POST heritage) and collapses duplicate keys down to
     * the last occurrence — both of which would corrupt an already-present query string before merging
     * in the new params. This preserves the original key text and groups duplicate keys into an array
     * instead of losing all but the last value. A literal "+" still decodes to a space first, same as
     * parse_str()/urldecode() — only rawurldecode() on its own would treat it as a literal plus and
     * corrupt any pre-existing "?q=a+b"-style query string the moment withQuery() touches it.
     *
     * @return array<string, mixed>
     */
    private function parseQueryString(string $query): array
    {
        if ($query === '') {
            return [];
        }

        $result = [];
        foreach (explode('&', $query) as $segment) {
            if ($segment === '') {
                continue;
            }

            $pair = explode('=', $segment, 2);
            $key = $this->decodeQueryComponent($pair[0]);
            $value = isset($pair[1]) ? $this->decodeQueryComponent($pair[1]) : '';

            if (array_key_exists($key, $result)) {
                $result[$key] = is_array($result[$key]) ? [...$result[$key], $value] : [$result[$key], $value];

                continue;
            }

            $result[$key] = $value;
        }

        return $result;
    }

    private function decodeQueryComponent(string $component): string
    {
        return rawurldecode(str_replace('+', ' ', $component));
    }

    public function withBody(string $body): self
    {
        $copy = clone $this;
        $copy->body = $body;
        return $copy;
    }

    /**
     * @param list<MultipartPart|array<string, mixed>> $parts
     */
    public function withMultipart(array $parts): self
    {
        $stream = new MultipartStream($parts);
        $copy = clone $this;
        $copy->body = $stream;

        return $this->replaceHeader($copy, 'Content-Type', 'multipart/form-data; boundary=' . $stream->boundary());
    }

    /** @param array<array-key, mixed>|JsonSerializable $data @throws JsonException */
    public function withJson(array|JsonSerializable $data): self
    {
        $replaceMultipartType = $this->body instanceof MultipartStream;
        $copy = $this->withBody(json_encode($data, JSON_THROW_ON_ERROR));
        if ($replaceMultipartType) {
            return $this->replaceHeader($copy, 'Content-Type', 'application/json');
        }

        foreach (array_keys($copy->headers) as $header) {
            if (strcasecmp($header, 'Content-Type') === 0) {
                return $copy;
            }
        }

        return $copy->withHeader('Content-Type', 'application/json');
    }
    public function withTimeout(float $seconds): self
    {
        return $this->withOptions(timeout: $seconds);
    }
    public function withConnectTimeout(float $seconds): self
    {
        return $this->withOptions(connectTimeout: $seconds);
    }
    /** @param string|array<string, string> $proxy */
    public function withProxy(string|array $proxy): self
    {
        return $this->withOptions(proxy: $proxy);
    }
    public function withVerifySsl(bool $verify): self
    {
        return $this->withOptions(verifySsl: $verify);
    }
    /** @param bool|array<string, mixed> $allow */
    public function withRedirects(bool|array $allow): self
    {
        return $this->withOptions(allowRedirects: $allow);
    }

    public function build(): PreparedRequest
    {
        if ($this->built !== null) {
            return $this->built;
        }

        if ($this->uri === '') {
            throw new InvalidConfigurationException('A request URI is required.');
        }
        $body = $this->body instanceof StreamInterface ? $this->body : $this->streamFactory->createStream($this->body);
        $request = $this->requestFactory->createRequest($this->method, $this->uri)->withBody($body);
        foreach ($this->headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $this->built = new PreparedRequest($request, $this->options);
    }

    public function __clone(): void
    {
        $this->built = null;
    }
    public function toPsr(): RequestInterface
    {
        return $this->build()->toPsr();
    }
    public function options(): RequestOptions
    {
        return $this->build()->options();
    }

    private function replaceHeader(self $copy, string $name, string $value): self
    {
        foreach (array_keys($copy->headers) as $header) {
            if (strcasecmp($header, $name) === 0) {
                unset($copy->headers[$header]);
            }
        }

        return $copy->withHeader($name, $value);
    }

    /**
     * @param string|array<string, string>|null $proxy
     * @param bool|array<string, mixed>|null $allowRedirects
     */
    private function withOptions(?float $timeout = null, ?float $connectTimeout = null, string|array|null $proxy = null, ?bool $verifySsl = null, bool|array|null $allowRedirects = null): self
    {
        $current = $this->options;
        $copy = clone $this;
        $copy->options = new RequestOptions(
            $timeout ?? $current->timeout,
            $connectTimeout ?? $current->connectTimeout,
            $proxy ?? $current->proxy,
            $verifySsl ?? $current->verifySsl,
            $allowRedirects ?? $current->allowRedirects,
            $current->extra,
        );
        return $copy;
    }
}
