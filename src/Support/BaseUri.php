<?php

declare(strict_types=1);

namespace JOOservices\Client\Support;

final class BaseUri
{
    public function normalize(string $uri): string
    {
        if ($uri === '') {
            return '';
        }

        $parts = parse_url($uri);
        $host = is_array($parts) ? ($parts['host'] ?? '') : '';
        if ($host === '') {
            return str_ends_with($uri, '/') ? $uri : $uri . '/';
        }

        return $this->prefix(is_array($parts) ? $parts : [], $this->directoryPath(is_array($parts) ? ($parts['path'] ?? '/') : '/'));
    }

    private function directoryPath(mixed $path): string
    {
        $value = is_string($path) && $path !== '' ? $path : '/';

        return str_ends_with($value, '/') ? $value : $value . '/';
    }

    /** @param array<array-key, mixed> $parts */
    private function prefix(array $parts, string $path): string
    {
        $scheme = $this->stringPart($parts, 'scheme');
        $host = $this->stringPart($parts, 'host');
        $user = $this->stringPart($parts, 'user');
        $pass = $this->stringPart($parts, 'pass');
        $auth = $user === '' ? '' : $user . ($pass === '' ? '' : ':' . $pass) . '@';
        $port = isset($parts['port']) && is_int($parts['port']) ? ':' . $parts['port'] : '';
        $query = $this->stringPart($parts, 'query');
        $fragment = $this->stringPart($parts, 'fragment');

        return ($scheme === '' ? '' : $scheme . '://')
            . $auth
            . $host
            . $port
            . $path
            . ($query === '' ? '' : '?' . $query)
            . ($fragment === '' ? '' : '#' . $fragment);
    }

    /** @param array<array-key, mixed> $parts */
    private function stringPart(array $parts, string $key): string
    {
        return isset($parts[$key]) && is_string($parts[$key]) ? $parts[$key] : '';
    }
}
