<?php

declare(strict_types=1);

namespace JOOservices\Client\Dto;

use JOOservices\Dto\Core\Dto;

final class TraceContextConfig extends Dto
{
    public function __construct(public readonly string $header = 'traceparent')
    {
    }
}
