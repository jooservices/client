<?php

declare(strict_types=1);

namespace JOOservices\Client\Contracts;

interface MetricsRecorderInterface
{
    /** @param array<string, scalar> $tags */
    public function increment(string $metric, array $tags = []): void;

    /** @param array<string, scalar> $tags */
    public function observe(string $metric, float $value, array $tags = []): void;
}
