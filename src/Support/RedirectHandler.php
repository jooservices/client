<?php

declare(strict_types=1);

namespace JOOservices\Client\Support;

use JOOservices\Client\Dto\RequestOptions;
use JOOservices\Client\Exceptions\RequestException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;

final class RedirectHandler
{
    private const REDIRECT_STATUSES = [301, 302, 303, 307, 308];

    private const SENSITIVE_HEADERS = [
        'authorization',
        'cookie',
        'cookie2',
        'proxy-authorization',
        'api-key',
        'x-api-key',
        'x-amz-security-token',
        'token',
        'x-token',
        'x-access-token',
        'access-token',
        'x-secret',
        'secret',
        'x-csrf-token',
        'x-session-token',
    ];

    private const SENSITIVE_NEEDLES = ['token', 'secret', 'password', 'credential'];

    public function __construct(
        private readonly UriFactoryInterface $uriFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly UriResolver $uris = new UriResolver(),
        private readonly RedirectTargetPolicy $targets = new RedirectTargetPolicy(),
    ) {
    }

    /**
     * @param callable(RequestInterface, RequestOptions, list<string>|null): ResponseInterface $send The
     *   3rd argument, when not null, is the exact set of public addresses RedirectTargetPolicy just
     *   verified for that request's host — a transport that can pin its connection to them (curl via
     *   CURLOPT_RESOLVE) should, to close the DNS-rebinding TOCTOU window between this check and the
     *   real connect. A transport that can't honor it is free to ignore the argument.
     */
    public function send(RequestInterface $request, RequestOptions $options, callable $send): ResponseInterface
    {
        $allow = $options->allowRedirects;
        $inner = $this->withoutFollowing($options);

        if ($allow === false) {
            return $send($request, $inner, null);
        }

        $max = $this->maxHops($allow);
        $current = $request;
        $response = $send($current, $inner, null);
        $hops = 0;

        while ($hops < $max && $this->isRedirect($response)) {
            $location = $response->getHeaderLine('Location');
            if ($location === '') {
                return $response;
            }

            $nextUri = $this->uris->resolve($current->getUri(), $this->uriFactory->createUri($location));
            $this->assertHttpUri($current, $nextUri);
            $pinnedAddresses = $this->targets->assertAllowed($current, $nextUri, $options);
            $next = $this->nextRequest($current, $nextUri, $response->getStatusCode(), $options);
            $current = $next;
            $response = $send($current, $inner, $pinnedAddresses);
            ++$hops;
        }

        if ($hops >= $max && $this->isRedirect($response)) {
            throw new RequestException($current, sprintf('Exceeded the redirect limit of %d.', $max));
        }

        return $response;
    }

    /** @param bool|array<string, mixed>|null $allow */
    private function maxHops(bool|array|null $allow): int
    {
        if (is_array($allow) && isset($allow['max']) && is_int($allow['max']) && $allow['max'] >= 0) {
            return $allow['max'];
        }

        return 5;
    }

    private function withoutFollowing(RequestOptions $options): RequestOptions
    {
        return new RequestOptions(
            $options->timeout,
            $options->connectTimeout,
            $options->proxy,
            $options->verifySsl,
            false,
            $options->extra,
        );
    }

    private function isRedirect(ResponseInterface $response): bool
    {
        return in_array($response->getStatusCode(), self::REDIRECT_STATUSES, true);
    }

    private function nextRequest(RequestInterface $current, UriInterface $nextUri, int $status, RequestOptions $options): RequestInterface
    {
        $hostChanged = strcasecmp($current->getUri()->getHost(), $nextUri->getHost()) !== 0
            || $current->getUri()->getPort() !== $nextUri->getPort()
            || strcasecmp($current->getUri()->getScheme(), $nextUri->getScheme()) !== 0;

        $next = $current->withUri($nextUri, ! $hostChanged);

        if ($hostChanged) {
            foreach (array_keys($next->getHeaders()) as $header) {
                if ($this->isSensitiveHeader($header, $options)) {
                    $next = $next->withoutHeader($header);
                }
            }
        }

        if (in_array($status, [301, 302, 303], true) && ! in_array(strtoupper($next->getMethod()), ['GET', 'HEAD'], true)) {
            $next = $next
                ->withMethod('GET')
                ->withBody($this->streamFactory->createStream(''))
                ->withoutHeader('Content-Length')
                ->withoutHeader('Content-Type')
                ->withoutHeader('Transfer-Encoding');
        }

        return $next;
    }

    private function assertHttpUri(RequestInterface $request, UriInterface $uri): void
    {
        $scheme = strtolower($uri->getScheme());
        if ($scheme !== 'http' && $scheme !== 'https') {
            throw new RequestException($request, 'Redirects are limited to HTTP and HTTPS.');
        }
    }

    private function isSensitiveHeader(string $header, RequestOptions $options): bool
    {
        $normalized = strtolower($header);
        if (
            in_array($normalized, self::SENSITIVE_HEADERS, true)
            || str_starts_with($normalized, 'x-auth-')
            || str_starts_with($normalized, 'x-api-')
        ) {
            return true;
        }

        foreach (self::SENSITIVE_NEEDLES as $needle) {
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }

        if (! is_array($options->allowRedirects)) {
            return false;
        }

        $configured = $options->allowRedirects['sensitive_headers'] ?? [];
        if (! is_array($configured)) {
            return false;
        }

        return in_array($normalized, array_map('strtolower', array_filter($configured, 'is_string')), true);
    }
}
