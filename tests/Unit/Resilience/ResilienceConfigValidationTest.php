<?php

declare(strict_types=1);

namespace JOOservices\Client\Tests\Unit\Resilience;

use JOOservices\Client\Exceptions\InvalidConfigurationException;
use JOOservices\Client\Resilience\BulkheadConfig;
use JOOservices\Client\Resilience\CircuitBreakerConfig;
use JOOservices\Client\Resilience\RateLimitConfig;
use JOOservices\Client\Resilience\RetryConfig;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ResilienceConfigValidationTest extends TestCase
{
    #[Test]
    public function testRetryConfigRejectsInvalidValues(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        new RetryConfig(maxAttempts: 0);
    }

    #[Test]
    public function testRetryConfigRejectsNegativeDelay(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        new RetryConfig(delayMilliseconds: -1);
    }

    #[Test]
    public function testCircuitBreakerConfigRejectsInvalidValues(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        new CircuitBreakerConfig(failureThreshold: 0);
    }

    #[Test]
    public function testCircuitBreakerConfigRejectsNonPositiveResetWindow(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        new CircuitBreakerConfig(resetAfterSeconds: 0.0);
    }

    #[Test]
    public function testRateLimitConfigRejectsInvalidValues(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        new RateLimitConfig(maxRequests: 0);
    }

    #[Test]
    public function testRateLimitConfigRejectsNonPositivePeriod(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        new RateLimitConfig(perSeconds: 0.0);
    }

    #[Test]
    public function testBulkheadConfigRejectsInvalidValues(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        new BulkheadConfig(maxConcurrent: 0);
    }
}
