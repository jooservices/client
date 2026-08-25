<?php

declare(strict_types=1);

namespace JOOservices\Client\Contracts;

interface TokenProviderInterface
{
    public function token(): string;

    public function refresh(): string;
}
