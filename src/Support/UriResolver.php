<?php

declare(strict_types=1);

namespace JOOservices\Client\Support;

use Psr\Http\Message\UriInterface;

final class UriResolver
{
    public function resolve(UriInterface $base, UriInterface $relative): UriInterface
    {
        if ($relative->getScheme() !== '') {
            return $relative->withPath($this->removeDotSegments($relative->getPath()));
        }

        if ($relative->getAuthority() !== '') {
            return $base
                ->withFragment($relative->getFragment())
                ->withUserInfo($relative->getUserInfo())
                ->withHost($relative->getHost())
                ->withPort($relative->getPort())
                ->withPath($this->removeDotSegments($relative->getPath()))
                ->withQuery($relative->getQuery());
        }

        return $this->resolveWithoutAuthority($base, $relative);
    }

    private function resolveWithoutAuthority(UriInterface $base, UriInterface $relative): UriInterface
    {
        $target = $base->withFragment($relative->getFragment());
        if ($relative->getPath() === '') {
            return $relative->getQuery() === '' ? $target : $target->withQuery($relative->getQuery());
        }

        $path = str_starts_with($relative->getPath(), '/')
            ? $relative->getPath()
            : $this->mergePath($base->getPath(), $relative->getPath());

        return $target
            ->withPath($this->removeDotSegments($path))
            ->withQuery($relative->getQuery());
    }

    private function mergePath(string $basePath, string $relativePath): string
    {
        $slash = strrpos($basePath, '/');
        if ($basePath === '' || $slash === false) {
            return '/' . $relativePath;
        }

        return substr($basePath, 0, $slash + 1) . $relativePath;
    }

    private function removeDotSegments(string $path): string
    {
        if ($path === '' || $path === '/') {
            return $path;
        }

        $stack = [];
        foreach (explode('/', $path) as $segment) {
            $this->pushSegment($stack, $segment);
        }

        return $this->joinSegments($path, $stack);
    }

    /** @param list<string> $stack */
    private function joinSegments(string $path, array $stack): string
    {
        $resolved = implode('/', $stack);
        if (str_starts_with($path, '/')) {
            $resolved = '/' . $resolved;
        }

        if (str_ends_with($path, '/') && $resolved !== '/' && ! str_ends_with($resolved, '/')) {
            $resolved .= '/';
        }

        if ($resolved !== '') {
            return $resolved;
        }

        return str_starts_with($path, '/') ? '/' : '';
    }

    /** @param list<string> $stack */
    private function pushSegment(array &$stack, string $segment): void
    {
        if ($segment === '' || $segment === '.') {
            return;
        }

        if ($segment === '..') {
            array_pop($stack);

            return;
        }

        $stack[] = $segment;
    }
}
