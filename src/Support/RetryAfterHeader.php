<?php

declare(strict_types=1);

namespace JOOservices\Client\Support;

final class RetryAfterHeader
{
    /** Safety cap regardless of what the server claims, so a hostile/broken Retry-After can't block forever. */
    private const DEFAULT_MAX_MILLISECONDS = 300_000;

    public function __construct(private readonly int $maxMilliseconds = self::DEFAULT_MAX_MILLISECONDS)
    {
    }

    public function milliseconds(string $value, int $fallback): int
    {
        if (ctype_digit($value)) {
            // Cast to float before multiplying: an absurdly large digit string, multiplied as an int,
            // can overflow into a float and violate this method's `int` return type under strict_types.
            // Float arithmetic never overflows to a type error; the clamp below brings it back to a safe int range.
            return $this->clamp((float) $value * 1000);
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? $this->clamp((float) $fallback) : $this->clamp(max(0.0, (float) ($timestamp - time())) * 1000);
    }

    private function clamp(float $milliseconds): int
    {
        return (int) max(0.0, min($milliseconds, (float) $this->maxMilliseconds));
    }
}
