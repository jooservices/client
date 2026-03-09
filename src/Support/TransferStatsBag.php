<?php

declare(strict_types=1);

namespace JOOservices\Client\Support;

final class TransferStatsBag
{
    public ?string $targetIp = null;

    public ?string $localIp = null;

    public ?string $effectiveUri = null;
}
