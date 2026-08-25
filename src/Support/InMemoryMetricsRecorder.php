<?php

declare(strict_types=1);

namespace JOOservices\Client\Support;

use JOOservices\Client\Contracts\MetricsRecorderInterface;

final class InMemoryMetricsRecorder implements MetricsRecorderInterface
{
    /** Per-key cap so a long-running process doesn't grow this array forever; a production workload belongs on a real metrics backend (StatsD, Prometheus, ...) via MetricsRecorderInterface, not this one. */
    private const DEFAULT_MAX_OBSERVATIONS_PER_KEY = 1000;

    /** @var array<string, list<float>> */
    private array $values = [];

    public function __construct(private readonly int $maxPerKey = self::DEFAULT_MAX_OBSERVATIONS_PER_KEY)
    {
    }

    public function increment(string $metric, array $tags = []): void
    {
        $this->observe($metric, 1.0, $tags);
    }

    public function observe(string $metric, float $value, array $tags = []): void
    {
        $key = $metric . ':' . json_encode($tags, JSON_THROW_ON_ERROR);
        $this->values[$key][] = $value;
        if (count($this->values[$key]) > $this->maxPerKey) {
            array_shift($this->values[$key]);
        }
    }

    /** @return array<string, list<float>> */
    public function values(): array
    {
        return $this->values;
    }
}
