<?php

declare(strict_types=1);

namespace JOOservices\Client\Contracts;

interface WanIpProviderInterface
{
    public function address(): string;
}
