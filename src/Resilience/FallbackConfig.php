<?php

declare(strict_types=1);

namespace JOOservices\Client\Resilience;

use JOOservices\Dto\Core\Dto;

final class FallbackConfig extends Dto
{
    public function __construct(public readonly bool $onNetworkFailure = true, public readonly bool $onServerError = false)
    {
    }
}
