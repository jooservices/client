<?php

declare(strict_types=1);

namespace JOOservices\Client\Dto;

use JOOservices\Dto\Core\Dto;

final class CacheConfig extends Dto
{
    /** @param list<string> $credentialHeaders Header names whose value distinguishes the caller; bound into the cache key so different callers never share a cached response. */
    public function __construct(
        public readonly int $ttl = 60,
        public readonly array $credentialHeaders = ['Authorization', 'X-Api-Key', 'X-Auth-Token'],
    ) {
    }
}
