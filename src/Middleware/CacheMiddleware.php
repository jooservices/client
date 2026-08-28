<?php

declare(strict_types=1);

namespace JOOservices\Client\Middleware;

use JOOservices\Client\Cache\ArrayCache;
use JOOservices\Client\Contracts\MiddlewareInterface;
use JOOservices\Client\Contracts\RequestHandlerInterface;
use JOOservices\Client\Dto\CacheConfig;
use JOOservices\Client\Dto\RequestOptions;
use JOOservices\Client\Exceptions\InvalidConfigurationException;
use JOOservices\Client\Support\StreamContents;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class CacheMiddleware implements MiddlewareInterface
{
    private readonly object $cache;

    public function __construct(
        private readonly ResponseFactoryInterface $responses,
        private readonly StreamFactoryInterface $streams,
        private readonly CacheConfig $config = new CacheConfig(),
        ?object $cache = null,
        private readonly StreamContents $contents = new StreamContents(),
    ) {
        $this->cache = $cache ?? new ArrayCache();
        foreach (['get', 'set'] as $method) {
            if (! method_exists($this->cache, $method)) {
                throw new InvalidConfigurationException('Cache must implement get() and set() (PSR-16).');
            }
        }
    }

    public function process(RequestInterface $request, RequestOptions $options, RequestHandlerInterface $handler): ResponseInterface
    {
        if (strtoupper($request->getMethod()) !== 'GET') {
            return $handler->handle($request, $options);
        }

        $key = 'http:' . hash('sha256', (string) $request->getUri() . "\n" . $this->principal($request));
        $cached = $this->get($key);
        if (is_array($cached)) {
            /** @var array<string, mixed> $cached */
            if ($this->varyMatches($cached, $request)) {
                return $this->hydrate($cached);
            }
        }

        $response = $handler->handle($request, $options);
        $cacheControl = strtolower($response->getHeaderLine('Cache-Control'));
        if ($this->isStoreable($response, $cacheControl)) {
            $response = $this->put($key, $request, $response, $cacheControl);
        }

        return $response;
    }

    private function principal(RequestInterface $request): string
    {
        $parts = [];
        foreach ($this->config->credentialHeaders as $name) {
            if ($request->hasHeader($name)) {
                // Hash each header individually before concatenating: a raw '&'-joined string would let
                // a caller craft one header value that collides with another caller's multi-header
                // combination. Fixed-length digests concatenated with no delimiter can't be shifted like
                // that, and forging a match would require a SHA-256 second preimage.
                $parts[] = hash('sha256', strtolower($name) . '=' . $request->getHeaderLine($name));
            }
        }

        return implode('', $parts);
    }

    private function isStoreable(ResponseInterface $response, string $cacheControl): bool
    {
        // Only 2xx: a cached 3xx would replay a stale redirect target without checking the origin again,
        // and this middleware sees a 3xx at all only when redirects are disabled or exhausted upstream.
        return $response->getStatusCode() >= 200
            && $response->getStatusCode() < 300
            && ! str_contains($cacheControl, 'no-store')
            && ! str_contains($cacheControl, 'private')
            && strtolower($response->getHeaderLine('Vary')) !== '*';
    }

    /** @param array<string, mixed> $cached */
    private function varyMatches(array $cached, RequestInterface $request): bool
    {
        $varyValues = $cached['varyValues'] ?? [];
        if (! is_array($varyValues)) {
            return true;
        }

        foreach ($varyValues as $name => $value) {
            if (is_string($name) && is_string($value) && $request->getHeaderLine($name) !== $value) {
                return false;
            }
        }

        return true;
    }

    private function get(string $key): mixed
    {
        /** @var callable $getter */
        $getter = [$this->cache, 'get'];

        return $getter($key);
    }

    private function put(string $key, RequestInterface $request, ResponseInterface $response, string $cacheControl): ResponseInterface
    {
        // read() can only preserve the cursor for a seekable stream; a non-seekable one is fully
        // drained by the getContents() call inside it, with no way to restore it. Rebuild the body from
        // the captured content so the caller gets a fresh, fully-readable stream either way.
        $body = $this->contents->read($response->getBody());
        $response = $response->withBody($this->streams->createStream($body));

        $headers = [];
        foreach ($response->getHeaders() as $name => $values) {
            // Never persist Set-Cookie*: a later caller that shares the cache key would otherwise
            // replay the origin's session cookies. Cookie is already part of the key principal.
            if (in_array(strtolower((string) $name), ['set-cookie', 'set-cookie2'], true)) {
                continue;
            }
            $headers[(string) $name] = array_values($values);
        }

        // Record the request-header values this response's Vary names, so a later request with
        // different values for them is treated as a miss instead of wrongly served this variant
        // (e.g. a compressed body served to a client that didn't send the matching Accept-Encoding).
        $varyValues = [];
        foreach ($this->varyHeaderNames($response) as $name) {
            $varyValues[$name] = $request->getHeaderLine($name);
        }

        /** @var callable $setter */
        $setter = [$this->cache, 'set'];
        $setter($key, [
            'status' => $response->getStatusCode(),
            'headers' => $headers,
            'body' => $body,
            'varyValues' => $varyValues,
        ], $this->effectiveTtl($response, $cacheControl));

        return $response;
    }

    /** @return list<string> */
    private function varyHeaderNames(ResponseInterface $response): array
    {
        $names = [];
        foreach (explode(',', $response->getHeaderLine('Vary')) as $name) {
            $name = trim($name);
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }

    /** Honors the origin's own freshness lifetime (Cache-Control: max-age, else Expires) as a ceiling on the configured TTL, not just a fixed local value. */
    private function effectiveTtl(ResponseInterface $response, string $cacheControl): int
    {
        $configured = max(0, $this->config->ttl);

        if (preg_match('/max-age=(\d+)/i', $cacheControl, $matches) === 1) {
            return min($configured, (int) $matches[1]);
        }

        $expires = $response->getHeaderLine('Expires');
        if ($expires !== '') {
            $timestamp = strtotime($expires);
            if ($timestamp !== false) {
                return max(0, min($configured, $timestamp - time()));
            }
        }

        return $configured;
    }

    /** @param array<string, mixed> $cached */
    private function hydrate(array $cached): ResponseInterface
    {
        $response = $this->responses->createResponse(is_int($cached['status'] ?? null) ? $cached['status'] : 200);
        $headers = $cached['headers'] ?? [];
        if (is_array($headers)) {
            foreach ($headers as $name => $values) {
                if (is_array($values)) {
                    /** @var list<string> $values */
                    $response = $response->withHeader((string) $name, $values);
                }
            }
        }

        return $response->withBody($this->streams->createStream(is_string($cached['body'] ?? null) ? $cached['body'] : ''));
    }
}
