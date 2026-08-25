<?php

declare(strict_types=1);

namespace JOOservices\Client\Testing;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

final class HttpFake
{
    public function __construct(private readonly string $method, private readonly string $pattern, private readonly TestResponseSequence $responses)
    {
    }

    public function matches(RequestInterface $request): bool
    {
        $method = strtoupper($this->method);
        if ($method !== '*' && $method !== strtoupper($request->getMethod())) {
            return false;
        }

        [$patternPath, $patternQuery] = $this->splitQuery($this->pattern);
        // A pattern without a scheme ("/users/*") is meant to match by path regardless of host; only a
        // pattern that names an absolute URI ("https://api.test/users/*") is compared against the full
        // URI. Query strings are matched separately, by key, so param order in the pattern or the
        // request never matters.
        $subject = str_contains($patternPath, '://')
            ? (string) $request->getUri()->withQuery('')
            : $request->getUri()->getPath();

        if (! fnmatch($patternPath, $subject)) {
            return false;
        }

        return $this->matchesQuery($patternQuery, $request);
    }

    /** @return array{0: string, 1: string} */
    private function splitQuery(string $pattern): array
    {
        $position = strpos($pattern, '?');

        return $position === false ? [$pattern, ''] : [substr($pattern, 0, $position), substr($pattern, $position + 1)];
    }

    private function matchesQuery(string $patternQuery, RequestInterface $request): bool
    {
        if ($patternQuery === '') {
            return true;
        }

        parse_str($patternQuery, $expected);
        parse_str($request->getUri()->getQuery(), $actual);

        foreach ($expected as $key => $value) {
            $actualValue = $actual[$key] ?? null;
            if (! is_string($value) || ! is_string($actualValue) || ! fnmatch($value, $actualValue)) {
                return false;
            }
        }

        return true;
    }

    public function next(): ResponseInterface|Throwable
    {
        return $this->responses->next();
    }

    public function isEmpty(): bool
    {
        return $this->responses->isEmpty();
    }
}
