<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use JOOservices\Client\Support\CachedExternalWanIpProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('unit')]
class CachedExternalWanIpProviderTest extends TestCase
{
    public function test_it_caches_wan_ip_within_ttl_window(): void
    {
        $calls = 0;

        $provider = new CachedExternalWanIpProvider(
            cacheTtlSeconds: 60,
            resolver: function () use (&$calls): ?string {
                $calls++;

                return '198.51.100.10';
            }
        );

        $this->assertSame('198.51.100.10', $provider->getPublicIp());
        $this->assertSame('198.51.100.10', $provider->getPublicIp());
        $this->assertSame(1, $calls);
    }

    public function test_it_keeps_last_cached_wan_ip_when_resolver_fails(): void
    {
        $calls = 0;

        $provider = new CachedExternalWanIpProvider(
            cacheTtlSeconds: 0,
            resolver: function () use (&$calls): ?string {
                $calls++;

                if ($calls === 1) {
                    return '198.51.100.11';
                }

                throw new \RuntimeException('resolver unavailable');
            }
        );

        $this->assertSame('198.51.100.11', $provider->getPublicIp());
        $this->assertSame('198.51.100.11', $provider->getPublicIp());
        $this->assertSame(2, $calls);
    }

    public function test_it_returns_null_when_no_cached_value_exists_and_resolver_fails(): void
    {
        $provider = new CachedExternalWanIpProvider(
            cacheTtlSeconds: 0,
            resolver: function (): ?string {
                throw new \RuntimeException('network down');
            }
        );

        $this->assertNull($provider->getPublicIp());
    }
}
