<?php

declare(strict_types=1);

namespace JOOservices\Client\Tests\Unit\Dto;

use JOOservices\Client\Dto\ClientConfig;
use JOOservices\Client\Exceptions\InvalidConfigurationException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ClientConfigTest extends TestCase
{
    #[Test]
    public function testRejectsANonPositiveTimeout(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        new ClientConfig(timeout: 0.0);
    }

    #[Test]
    public function testRejectsANonPositiveConnectTimeout(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        new ClientConfig(connectTimeout: -1.0);
    }
}
