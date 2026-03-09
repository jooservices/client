<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObjects;

use JOOservices\Client\Exceptions\InvalidConfigurationException;
use JOOservices\Client\ValueObjects\ClientConfig;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('unit')]
class ClientConfigTest extends TestCase
{
    public function test_it_sets_defaults_correctly(): void
    {
        $config = new ClientConfig();
        $this->assertSame(30, $config->timeout);
        $this->assertSame(10, $config->connectTimeout);
        $this->assertTrue($config->verifySsl);
    }

    public function test_it_converts_to_guzzle_options(): void
    {
        $config = new ClientConfig(
            baseUri: 'https://example.com',
            timeout: 5,
            options: ['debug' => true]
        );

        $guzzle = $config->toGuzzleOptions();

        $this->assertIsArray($guzzle);
        $this->assertSame('https://example.com', $guzzle['base_uri']);
        $this->assertSame(5, $guzzle['timeout']);
        $this->assertTrue($guzzle['debug']);
        $this->assertFalse($guzzle['http_errors']);
    }

    public function test_it_throws_on_negative_timeout(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        new ClientConfig(timeout: -1);
    }

    public function test_it_throws_on_negative_connect_timeout(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Connect timeout cannot be negative');
        new ClientConfig(connectTimeout: -1);
    }

    public function test_create_from_array_with_options_resolver(): void
    {
        $config = ClientConfig::fromArray([
            'timeout' => 50,
            'baseUri' => 'https://api.test.com',
        ]);

        $this->assertSame(50, $config->timeout);
        $this->assertSame('https://api.test.com', $config->baseUri);
    }
}
