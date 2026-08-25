<?php

declare(strict_types=1);

namespace JOOservices\Client\Transport\Curl;

use JOOservices\Client\Dto\RequestOptions;
use Psr\Http\Message\RequestInterface;

final class CurlProxy
{
    public function resolve(RequestInterface $request, RequestOptions $options): ?string
    {
        $proxy = $options->proxy;
        if ($proxy === null) {
            return null;
        }

        if (is_string($proxy)) {
            return $proxy;
        }

        $host = $request->getUri()->getHost();
        if (array_key_exists('no', $proxy) && $this->hostIsExcluded($host, $proxy['no'])) {
            return null;
        }

        $scheme = strtolower($request->getUri()->getScheme());
        if (array_key_exists($scheme, $proxy) && is_string($proxy[$scheme])) {
            return $proxy[$scheme];
        }

        return null;
    }

    private function hostIsExcluded(string $host, mixed $noProxy): bool
    {
        foreach ($this->patterns($noProxy) as $pattern) {
            if ($this->matches($host, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function patterns(mixed $noProxy): array
    {
        if (is_array($noProxy)) {
            return array_values(array_filter($noProxy, static fn(mixed $item): bool => is_string($item) && $item !== ''));
        }

        if (! is_string($noProxy)) {
            return [];
        }

        $split = preg_split('/[\s,]+/', $noProxy);

        return $split === false ? [] : $split;
    }

    private function matches(string $host, string $pattern): bool
    {
        if ($pattern === '') {
            return false;
        }

        // curl's own NO_PROXY convention treats a bare entry ("example.com") the same as a
        // leading-dot one (".example.com"): both match the exact host and any subdomain. Only
        // requiring the leading dot would silently keep subdomains routed through the proxy when a
        // caller wrote NO_PROXY entries the way curl itself documents them.
        $domain = strtolower(ltrim($pattern, '.'));
        $host = strtolower($host);

        return $host === $domain || str_ends_with($host, '.' . $domain);
    }
}
