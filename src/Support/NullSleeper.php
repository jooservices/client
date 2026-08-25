<?php

declare(strict_types=1);

namespace JOOservices\Client\Support;

use JOOservices\Client\Contracts\SleeperInterface;

final class NullSleeper implements SleeperInterface
{
    public function sleep(int $milliseconds): void
    {
        unset($milliseconds);
    }
}
