<?php

declare(strict_types=1);

namespace JOOservices\Client\Support;

// RedirectTargetPolicy calls the unqualified `dns_get_record()`, so PHP resolves it against this
// namespace before falling back to the global built-in. Stubbing it here keeps RedirectHandlerTest
// hermetic instead of depending on live DNS resolution of real external domains, which is flaky on
// network-restricted CI runners even though the domains themselves are never actually contacted.
function dns_get_record(string $hostname, int $type = DNS_A): array|false
{
    $addresses = match ($hostname) {
        'abc.com', 'example.com', 'attacker.co.uk', 'victim.co.uk' => ['93.184.216.34'],
        'cgnat.example' => ['100.64.0.1'],
        'private.example' => ['10.0.0.1'],
        'protocol-assignment.example' => ['192.0.0.8'],
        'benchmark.example' => ['198.18.0.1'],
        'nat64.example' => ['64:ff9b::1'],
        '6to4.example' => ['2002:c000:0201::1'],
        'global-192-0-0-9.example' => ['192.0.0.9'],
        'global-192-0-0-10.example' => ['192.0.0.10'],
        default => [],
    };

    return array_map(
        static fn(string $address): array => str_contains($address, ':')
            ? ['host' => $hostname, 'type' => 'AAAA', 'ipv6' => $address]
            : ['host' => $hostname, 'type' => 'A', 'ip' => $address],
        $addresses,
    );
}
