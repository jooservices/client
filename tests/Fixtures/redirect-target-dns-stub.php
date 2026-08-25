<?php

declare(strict_types=1);

namespace JOOservices\Client\Support;

// RedirectTargetPolicy calls the unqualified `dns_get_record()`, so PHP resolves it against this
// namespace before falling back to the global built-in. Stubbing it here keeps RedirectHandlerTest
// hermetic instead of depending on live DNS resolution of real external domains, which is flaky on
// network-restricted CI runners even though the domains themselves are never actually contacted.
function dns_get_record(string $hostname, int $type = DNS_A): array|false
{
    return match ($hostname) {
        'abc.com', 'example.com' => [['host' => $hostname, 'type' => 'A', 'ip' => '93.184.216.34']],
        default => [],
    };
}
