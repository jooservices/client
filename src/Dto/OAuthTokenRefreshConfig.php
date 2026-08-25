<?php

declare(strict_types=1);

namespace JOOservices\Client\Dto;

use JOOservices\Dto\Core\Dto;

final class OAuthTokenRefreshConfig extends Dto
{
    public function __construct(
        public readonly string $header = 'Authorization',
        public readonly string $prefix = 'Bearer',
        /** Minimum time between refresh() calls, so permanently-bad credentials don't hammer the token endpoint on every request. */
        public readonly float $refreshCooldown = 5.0,
    ) {
    }
}
