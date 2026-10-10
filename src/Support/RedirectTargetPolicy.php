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

        $literalAddress = $this->literalIpAddress($target->getHost());
        if ($literalAddress !== null) {
            if (! $this->isPublicAddress($literalAddress)) {
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

    private function literalIpAddress(string $host): ?string
    {
        $address = str_starts_with($host, '[') && str_ends_with($host, ']')
            ? substr($host, 1, -1)
            : $host;

        return filter_var($address, FILTER_VALIDATE_IP) === false ? null : $address;
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
        $addressBytes = inet_pton($address);
        if ($addressBytes === false) {
            return false;
        }

        if (strlen($addressBytes) === 4) {
            return $this->isPublicIpv4Address($address);
        }

        if ($this->isInRange($address, '64:ff9b::', 96)) {
            $embeddedAddress = inet_ntop(substr($addressBytes, 12, 4));

            return $embeddedAddress !== false && $this->isPublicIpv4Address($embeddedAddress);
        }

        return $this->isPublicUntranslatedAddress($address);
    }

    private function isPublicIpv4Address(string $address): bool
    {
        if (in_array($address, ['192.0.0.9', '192.0.0.10'], true)) {
            return true;
        }

        return $this->isPublicUntranslatedAddress($address);
    }

    private function isPublicUntranslatedAddress(string $address): bool
    {
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        return ! $this->isNonGlobalSpecialUseAddress($address);
    }

    private function isNonGlobalSpecialUseAddress(string $address): bool
    {
        $addressBytes = inet_pton($address);
        if ($addressBytes === false) {
            return true;
        }

        return strlen($addressBytes) === 4
            ? $this->isNonGlobalIpv4SpecialUseAddress($address)
            : $this->isNonGlobalIpv6SpecialUseAddress($address);
    }

    private function isNonGlobalIpv4SpecialUseAddress(string $address): bool
    {
        return $this->isInRange($address, '100.64.0.0', 10)
            || $this->isInRange($address, '192.0.0.0', 24)
            || $this->isInRange($address, '192.88.99.0', 24)
            || $this->isInRange($address, '198.18.0.0', 15);
    }

    private function isNonGlobalIpv6SpecialUseAddress(string $address): bool
    {
        if ($this->isInRange($address, '2001::', 23)) {
            return ! $this->isGloballyReachableIetfAllocation($address);
        }

        return $this->isInRange($address, '64:ff9b:1::', 48)
            || $this->isInRange($address, '100::', 64)
            || $this->isInRange($address, '100:0:0:1::', 64)
            || $this->isInRange($address, '2002::', 16)
            || $this->isInRange($address, '3fff::', 20)
            || $this->isInRange($address, '5f00::', 16);
    }

    private function isGloballyReachableIetfAllocation(string $address): bool
    {
        foreach (
            [
                ['2001:1::1', 128],
                ['2001:1::2', 128],
                ['2001:1::3', 128],
                ['2001:3::', 32],
                ['2001:4:112::', 48],
                ['2001:20::', 28],
                ['2001:30::', 28],
            ] as [$network, $prefixLength]
        ) {
            if ($this->isInRange($address, $network, $prefixLength)) {
                return true;
            }
        }

        return false;
    }

    private function isInRange(string $address, string $network, int $prefixLength): bool
    {
        $addressBytes = inet_pton($address);
        $networkBytes = inet_pton($network);
        if ($addressBytes === false || $networkBytes === false || strlen($addressBytes) !== strlen($networkBytes)) {
            return false;
        }

        $wholeBytes = intdiv($prefixLength, 8);
        if (substr($addressBytes, 0, $wholeBytes) !== substr($networkBytes, 0, $wholeBytes)) {
            return false;
        }

        $remainingBits = $prefixLength % 8;
        if ($remainingBits === 0) {
            return true;
        }

        $mask = 0xff << (8 - $remainingBits);

        return (ord($addressBytes[$wholeBytes]) & $mask) === (ord($networkBytes[$wholeBytes]) & $mask);
    }
}
