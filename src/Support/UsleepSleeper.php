<?php

declare(strict_types=1);

namespace JOOservices\Client\Support;

use JOOservices\Client\Contracts\SleeperInterface;

final class UsleepSleeper implements SleeperInterface
{
    public function sleep(int $milliseconds): void
    {
        if ($milliseconds > 0) {
            usleep($milliseconds * 1000);
        }
    }
}
