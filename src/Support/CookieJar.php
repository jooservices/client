<?php

declare(strict_types=1);

namespace JOOservices\Client\Support;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * Minimal RFC 6265 cookie store scoped to a single redirect chain. It captures the cookies a server
 * sets on one hop (Set-Cookie) and replays the ones whose domain, path and scheme match the next
 * hop (Cookie). It deliberately does not persist across separate send() calls — nothing is shared
 * between requests, so a cookie learned while following one redirect can never leak into an
 * unrelated request.
 */
final class CookieJar
{
    /** @var array<string, array{name: string, value: string, domain: string, path: string, secure: bool, hostOnly: bool, expires: int|null}> */
    private array $cookies = [];

    public function storeFromResponse(UriInterface $uri, ResponseInterface $response): void
    {
        foreach ($response->getHeader('Set-Cookie') as $header) {
            $this->store($uri, $header);
        }
    }

    public function headerFor(UriInterface $uri): ?string
    {
        $host = strtolower($uri->getHost());
        $path = $uri->getPath();
        if ($path === '') {
            $path = '/';
        }

        $secure = strtolower($uri->getScheme()) === 'https';
        $now = time();
        /** @var list<array{name: string, value: string, domain: string, path: string, secure: bool, hostOnly: bool, expires: int|null}> $matches */
        $matches = [];

        foreach ($this->cookies as $cookie) {
            if ($this->matches($cookie, $host, $path, $secure, $now)) {
                $matches[] = $cookie;
            }
        }

        if ($matches === []) {
            return null;
        }

        usort($matches, static fn(array $left, array $right): int => strlen($right['path']) <=> strlen($left['path']));

        return implode('; ', array_map(static fn(array $cookie): string => $cookie['name'] . '=' . $cookie['value'], $matches));
    }

    /**
     * @param array{name: string, value: string, domain: string, path: string, secure: bool, hostOnly: bool, expires: int|null} $cookie
     */
    private function matches(array $cookie, string $host, string $path, bool $secure, int $now): bool
    {
        if ($cookie['expires'] !== null && $cookie['expires'] <= $now) {
            return false;
        }

        if ($cookie['secure'] && ! $secure) {
            return false;
        }

        if (! $this->domainMatches($host, $cookie['domain'], $cookie['hostOnly'])) {
            return false;
        }

        return $this->pathMatches($path, $cookie['path']);
    }

    private function store(UriInterface $uri, string $header): void
    {
        $parsed = $this->parseSetCookie($header);
        if ($parsed === null) {
            return;
        }

        $cookie = $this->buildCookie($parsed, strtolower($uri->getHost()), $uri->getPath());
        if ($cookie === null) {
            return;
        }

        $key = $cookie['domain'] . "\0" . $cookie['path'] . "\0" . $cookie['name'];
        if ($cookie['expires'] !== null && $cookie['expires'] <= time()) {
            unset($this->cookies[$key]);

            return;
        }

        $this->cookies[$key] = $cookie;
    }

    /** @return array{name: string, value: string, attributes: list<string>}|null */
    private function parseSetCookie(string $header): ?array
    {
        $parts = explode(';', $header);
        $pair = trim(array_shift($parts));
        $separator = strpos($pair, '=');
        if ($separator === false) {
            return null;
        }

        $name = trim(substr($pair, 0, $separator));
        if ($name === '') {
            return null;
        }

        return [
            'name' => $name,
            'value' => trim(substr($pair, $separator + 1)),
            'attributes' => $parts,
        ];
    }

    /**
     * @param array{name: string, value: string, attributes: list<string>} $parsed
     * @return array{name: string, value: string, domain: string, path: string, secure: bool, hostOnly: bool, expires: int|null}|null
     */
    private function buildCookie(array $parsed, string $host, string $requestPath): ?array
    {
        $attributes = $this->attributeMap($parsed['attributes']);

        $domain = $host;
        $hostOnly = true;
        if (array_key_exists('domain', $attributes)) {
            $candidate = strtolower(ltrim($attributes['domain'], '.'));
            if ($candidate === '' || ! str_contains($candidate, '.') || ! $this->domainMatches($host, $candidate, false)) {
                return null;
            }
            // Without a public suffix list we cannot safely distinguish a registrable domain
            // (example.com) from a multi-label public suffix (co.uk). Keep the cookie scoped to
            // the response host instead of broadening it to the Domain attribute; this prevents
            // a redirect from replaying a public-suffix cookie to an unrelated registrable domain.
        }

        $path = $this->resolvePath($attributes, $requestPath);

        return [
            'name' => $parsed['name'],
            'value' => $parsed['value'],
            'domain' => $domain,
            'path' => $path,
            'secure' => array_key_exists('secure', $attributes),
            'hostOnly' => $hostOnly,
            'expires' => $this->expiry($attributes),
        ];
    }

    /**
     * @param list<string> $attributes
     * @return array<string, string>
     */
    private function attributeMap(array $attributes): array
    {
        $map = [];
        foreach ($attributes as $attribute) {
            $attribute = trim($attribute);
            if ($attribute === '') {
                continue;
            }

            $parts = array_pad(explode('=', $attribute, 2), 2, '');
            $map[strtolower(trim($parts[0]))] = trim($parts[1]);
        }

        return $map;
    }

    /** @param array<string, string> $attributes */
    private function resolvePath(array $attributes, string $requestPath): string
    {
        $configured = $attributes['path'] ?? '';
        if ($configured !== '' && str_starts_with($configured, '/')) {
            return $configured;
        }

        return $this->defaultPath($requestPath);
    }

    /** @param array<string, string> $attributes */
    private function expiry(array $attributes): ?int
    {
        if (array_key_exists('max-age', $attributes) && preg_match('/^-?\d+$/', $attributes['max-age']) === 1) {
            $maxAge = (int) $attributes['max-age'];

            return $maxAge <= 0 ? 1 : time() + $maxAge;
        }

        if (! array_key_exists('expires', $attributes)) {
            return null;
        }

        $timestamp = strtotime($attributes['expires']);

        return $timestamp === false ? null : $timestamp;
    }

    private function domainMatches(string $host, string $domain, bool $hostOnly): bool
    {
        if ($host === $domain) {
            return true;
        }

        if ($hostOnly || filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return false;
        }

        return str_ends_with($host, '.' . $domain);
    }

    private function pathMatches(string $requestPath, string $cookiePath): bool
    {
        if ($requestPath === $cookiePath) {
            return true;
        }

        if (! str_starts_with($requestPath, $cookiePath)) {
            return false;
        }

        if (str_ends_with($cookiePath, '/')) {
            return true;
        }

        return substr($requestPath, strlen($cookiePath), 1) === '/';
    }

    private function defaultPath(string $path): string
    {
        if ($path === '' || ! str_starts_with($path, '/')) {
            return '/';
        }

        $lastSlash = strrpos($path, '/');
        if ($lastSlash === false || $lastSlash === 0) {
            return '/';
        }

        return substr($path, 0, $lastSlash);
    }
}
