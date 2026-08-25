<?php

declare(strict_types=1);

namespace JOOservices\Client\Contracts;

interface SleeperInterface
{
    public function sleep(int $milliseconds): void;
}
