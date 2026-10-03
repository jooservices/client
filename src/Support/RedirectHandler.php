<?php

declare(strict_types=1);

namespace JOOservices\Client\Support;

use JOOservices\Client\Dto\RequestOptions;
use JOOservices\Client\Exceptions\RequestException;
use JOOservices\Client\Exceptions\TimeoutException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;

final class RedirectHandler
{
    private const REDIRECT_STATUSES = [301, 302, 303, 307, 308];

    private const EFFECTIVE_URI_HEADER = 'X-Joo-Effective-Uri';

    private const REDIRECT_HISTORY_HEADER = 'X-Joo-Redirect-History';

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
     *
     * Redirect options (the array form of allowRedirects) also understand:
     *   - total_timeout (float): a single budget in seconds shared by every hop, so the whole chain is
     *     bounded instead of each hop getting the full per-request timeout again.
     *   - track_redirects (bool): add X-Joo-Effective-Uri and X-Joo-Redirect-History to the response.
     *   - cookies (bool, default true): replay cookies a server set on an earlier hop to the next one.
     */
    public function send(RequestInterface $request, RequestOptions $options, callable $send): ResponseInterface
    {
        $allow = $options->allowRedirects;

        if ($allow === false) {
            return $send($request, $this->withoutFollowing($options), null);
        }

        return $this->follow($request, $options, $allow, $send);
    }

    /**
     * @param bool|array<string, mixed>|null $allow
     * @param callable(RequestInterface, RequestOptions, list<string>|null): ResponseInterface $send
     */
    private function follow(RequestInterface $request, RequestOptions $options, bool|array|null $allow, callable $send): ResponseInterface
    {
        $max = $this->maxHops($allow);
        $deadline = $this->deadline($allow);
        $track = $this->tracksRedirects($allow);
        $cookies = $this->collectsCookies($allow) ? new CookieJar() : null;

        $inner = $this->withoutFollowing($options);
        $current = $request;
        /** @var list<string> $history */
        $history = [];
        $response = $send($current, $this->hopOptions($inner, $deadline, $current), null);
        $cookies?->storeFromResponse($current->getUri(), $response);
        $hops = 0;

        while ($hops < $max && $this->isRedirect($response)) {
            if ($response->getHeaderLine('Location') === '') {
                break;
            }

            $step = $this->advance($current, $response, $options, $cookies, $deadline, $inner, $send, $history);
            $current = $step['request'];
            $response = $step['response'];
            ++$hops;
        }

        if ($hops >= $max && $this->isRedirect($response)) {
            throw new RequestException($current, sprintf('Exceeded the redirect limit of %d.', $max));
        }

        if ($track) {
            $response = $this->withRedirectTracking($response, $current->getUri(), $history);
        }

        return $response;
    }

    /**
     * @param callable(RequestInterface, RequestOptions, list<string>|null): ResponseInterface $send
     * @param list<string> $history
     * @return array{request: RequestInterface, response: ResponseInterface}
     */
    private function advance(
        RequestInterface $current,
        ResponseInterface $response,
        RequestOptions $options,
        ?CookieJar $cookies,
        ?int $deadline,
        RequestOptions $inner,
        callable $send,
        array &$history,
    ): array {
        $nextUri = $this->uris->resolve($current->getUri(), $this->uriFactory->createUri($response->getHeaderLine('Location')));
        $this->assertHttpUri($current, $nextUri);
        // A same-host redirect can't pivot to a different origin, so the public/private DNS guard
        // (which would fail closed on a transient lookup or a /etc/hosts-only host) is unnecessary.
        $pinnedAddresses = $this->isSameHost($current->getUri(), $nextUri)
            ? null
            : $this->targets->assertAllowed($current, $nextUri, $options);
        $next = $this->nextRequest($current, $nextUri, $response->getStatusCode(), $options);
        if ($cookies !== null) {
            $next = $this->withCookies($next, $cookies);
        }

        $history[] = (string) $nextUri;
        $nextResponse = $send($next, $this->hopOptions($inner, $deadline, $next), $pinnedAddresses);
        $cookies?->storeFromResponse($next->getUri(), $nextResponse);

        return ['request' => $next, 'response' => $nextResponse];
    }

    /** @param bool|array<string, mixed>|null $allow */
    private function maxHops(bool|array|null $allow): int
    {
        if (is_array($allow) && isset($allow['max']) && is_int($allow['max']) && $allow['max'] >= 0) {
            return $allow['max'];
        }

        return 5;
    }

    /** @param bool|array<string, mixed>|null $allow */
    private function deadline(bool|array|null $allow): ?int
    {
        if (! is_array($allow) || ! isset($allow['total_timeout']) || ! is_numeric($allow['total_timeout'])) {
            return null;
        }

        $seconds = (float) $allow['total_timeout'];
        if ($seconds <= 0.0) {
            return null;
        }

        return hrtime(true) + (int) round($seconds * 1_000_000_000);
    }

    /** @param bool|array<string, mixed>|null $allow */
    private function tracksRedirects(bool|array|null $allow): bool
    {
        return is_array($allow) && ($allow['track_redirects'] ?? false) === true;
    }

    /** @param bool|array<string, mixed>|null $allow */
    private function collectsCookies(bool|array|null $allow): bool
    {
        return ! is_array($allow) || ($allow['cookies'] ?? true) !== false;
    }

    private function hopOptions(RequestOptions $inner, ?int $deadline, RequestInterface $request): RequestOptions
    {
        if ($deadline === null) {
            return $inner;
        }

        $remaining = ($deadline - hrtime(true)) / 1_000_000_000;
        if ($remaining <= 0.0) {
            throw new TimeoutException($request, 'The redirect total timeout elapsed.');
        }

        return new RequestOptions(
            timeout: $inner->timeout === null ? $remaining : min($inner->timeout, $remaining),
            connectTimeout: $inner->connectTimeout,
            proxy: $inner->proxy,
            verifySsl: $inner->verifySsl,
            allowRedirects: false,
            extra: $inner->extra,
            compression: $inner->compression,
        );
    }

    private function isSameHost(UriInterface $current, UriInterface $next): bool
    {
        return strcasecmp($current->getHost(), $next->getHost()) === 0;
    }

    private function withCookies(RequestInterface $request, CookieJar $jar): RequestInterface
    {
        $jarHeader = $jar->headerFor($request->getUri());
        if ($jarHeader === null) {
            return $request;
        }

        $existing = $request->getHeaderLine('Cookie');
        if ($existing !== '') {
            $jarHeader = $existing . '; ' . $jarHeader;
        }

        return $request->withHeader('Cookie', $jarHeader);
    }

    /** @param list<string> $history */
    private function withRedirectTracking(ResponseInterface $response, UriInterface $effective, array $history): ResponseInterface
    {
        $response = $response->withHeader(self::EFFECTIVE_URI_HEADER, (string) $effective);
        if ($history !== []) {
            $response = $response->withHeader(self::REDIRECT_HISTORY_HEADER, implode(', ', $history));
        }

        return $response;
    }

    private function withoutFollowing(RequestOptions $options): RequestOptions
    {
        return new RequestOptions(
            timeout: $options->timeout,
            connectTimeout: $options->connectTimeout,
            proxy: $options->proxy,
            verifySsl: $options->verifySsl,
            allowRedirects: false,
            extra: $options->extra,
            compression: $options->compression,
        );
    }

    private function isRedirect(ResponseInterface $response): bool
    {
        return in_array($response->getStatusCode(), self::REDIRECT_STATUSES, true);
    }

    private function nextRequest(RequestInterface $current, UriInterface $nextUri, int $status, RequestOptions $options): RequestInterface
    {
        $currentUri = $current->getUri();
        $hostChanged = strcasecmp($currentUri->getHost(), $nextUri->getHost()) !== 0;
        // Cookies are not port-scoped, and upgrading http -> https is safe; only a cross-host hop or a
        // downgrade to plain http may leak a credential, so those are the cases that strip.
        $authorityChanged = $hostChanged || $currentUri->getPort() !== $nextUri->getPort();
        $downgraded = strtolower($currentUri->getScheme()) === 'https' && strtolower($nextUri->getScheme()) === 'http';

        $next = $current->withUri($nextUri, ! $authorityChanged);

        if ($hostChanged || $downgraded) {
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
