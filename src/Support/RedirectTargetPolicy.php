<?php

declare(strict_types=1);

namespace JOOservices\Client\Support;

use JOOservices\Client\Dto\RequestOptions;
use JOOservices\Client\Exceptions\RequestException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

/** Guards public-origin redirects from reaching private or unresolved hosts. */
final class RedirectTargetPolicy
{
    /**
     * @return list<string>|null The exact public addresses this check just verified for $target's
     *   host, so a caller that can pin a connection to them (CurlTransport via CURLOPT_RESOLVE) closes
     *   the DNS-rebinding TOCTOU window between this check and the actual connect — an attacker-controlled
     *   DNS server answering public here and private on the real connection would otherwise defeat this
     *   guard entirely. Null means there's nothing to pin (target is already a literal IP, or the check
     *   was skipped because private targets are allowed).
     */
    public function assertAllowed(RequestInterface $request, UriInterface $target, RequestOptions $options): ?array
    {
        if ($this->allowsPrivateTargets($options) || $this->isLiteralPrivateHost($request->getUri()->getHost())) {
            return null;
        }

        if (filter_var($target->getHost(), FILTER_VALIDATE_IP) !== false) {
            if (! $this->isPublicAddress($target->getHost())) {
                throw new RequestException($request, 'Redirect target resolves to a private, link-local, or unresolved address.');
            }

            return null;
        }

        $addresses = $this->resolve($target->getHost());
        if ($addresses === [] || $this->anyPrivate($addresses)) {
            throw new RequestException($request, 'Redirect target resolves to a private, link-local, or unresolved address.');
        }

        return $addresses;
    }

    /** @param list<string> $addresses */
    private function anyPrivate(array $addresses): bool
    {
        foreach ($addresses as $address) {
            if (! $this->isPublicAddress($address)) {
                return true;
            }
        }

        return false;
    }

    private function allowsPrivateTargets(RequestOptions $options): bool
    {
        return is_array($options->allowRedirects) && ($options->allowRedirects['allow_private'] ?? false) === true;
    }

    private function isLiteralPrivateHost(string $host): bool
    {
        if ($host === 'localhost') {
            return true;
        }

        return filter_var($host, FILTER_VALIDATE_IP) !== false && ! $this->isPublicAddress($host);
    }

    /** @return list<string> */
    private function resolve(string $host): array
    {
        set_error_handler(static fn(): bool => true);
        try {
            $records = dns_get_record($host, DNS_A | DNS_AAAA);
        } finally {
            restore_error_handler();
        }
        if ($records === false) {
            return [];
        }

        $addresses = [];
        foreach ($records as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;
            if (is_string($address)) {
                $addresses[] = $address;
            }
        }

        return $addresses;
    }

    private function isPublicAddress(string $address): bool
    {
        return filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }
}
