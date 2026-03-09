<?php

declare(strict_types=1);

namespace Tests\Unit\Logging;

use JOOservices\Client\Logging\MongoDbLogger;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('unit')]
class MongoDbLoggerTest extends TestCase
{
    public function test_captures_request_payload_with_trimming(): void
    {
        $documents = [];

        $logger = new MongoDbLogger(
            maxRequestBodyBytes: 5,
            writer: function (array $document) use (&$documents): void {
                $documents[] = $document;
            }
        );

        $logger->debug('Request Body', ['body' => 'abcdef']);

        $this->assertCount(1, $documents);
        $this->assertArrayHasKey('request_payload', $documents[0]);
        $this->assertSame('abcde', $documents[0]['request_payload']);
        $this->assertArrayHasKey('payload_truncated', $documents[0]);
        $this->assertTrue($documents[0]['payload_truncated']);
    }

    public function test_captures_response_payload_with_configurable_trimming(): void
    {
        $documents = [];

        $logger = new MongoDbLogger(
            maxResponseBodyBytes: 4,
            writer: function (array $document) use (&$documents): void {
                $documents[] = $document;
            }
        );

        $logger->debug('Response Body', ['body' => '123456']);

        $this->assertCount(1, $documents);
        $this->assertArrayHasKey('response_payload', $documents[0]);
        $this->assertSame('1234', $documents[0]['response_payload']);
        $this->assertArrayHasKey('payload_truncated', $documents[0]);
        $this->assertTrue($documents[0]['payload_truncated']);
    }

    public function test_maps_common_request_metadata_fields(): void
    {
        $documents = [];

        $logger = new MongoDbLogger(
            writer: function (array $document) use (&$documents): void {
                $documents[] = $document;
            }
        );

        $logger->info('Sending request to GET https://example.com', [
            'method' => 'GET',
            'uri' => 'https://example.com',
            'correlation_id' => 'abc-123',
        ]);

        $this->assertCount(1, $documents);
        $this->assertArrayHasKey('method', $documents[0]);
        $this->assertSame('GET', $documents[0]['method']);
        $this->assertArrayHasKey('uri', $documents[0]);
        $this->assertSame('https://example.com', $documents[0]['uri']);
        $this->assertArrayHasKey('correlation_id', $documents[0]);
        $this->assertSame('abc-123', $documents[0]['correlation_id']);
    }

    public function test_redacts_sensitive_headers_in_context(): void
    {
        $documents = [];

        $logger = new MongoDbLogger(
            writer: function (array $document) use (&$documents): void {
                $documents[] = $document;
            }
        );

        $logger->debug('Request Body', [
            'headers' => [
                'Authorization' => 'Bearer secret',
                'X-Test' => 'safe',
            ],
            'body' => 'ok',
        ]);

        $this->assertCount(1, $documents);
        $this->assertSame('[REDACTED]', $documents[0]['context']['headers']['Authorization']);
        $this->assertSame('safe', $documents[0]['context']['headers']['X-Test']);
    }

    public function test_requires_non_negative_trim_values(): void
    {
        $this->expectException(\RuntimeException::class);
        new MongoDbLogger(maxRequestBodyBytes: -1, writer: static function (array $d): void {
        });
    }

    public function test_requires_non_negative_trim_values_response(): void
    {
        $this->expectException(\RuntimeException::class);
        new MongoDbLogger(maxResponseBodyBytes: -1, writer: static function (array $d): void {
        });
    }

    /**
     * Ensures log() swallows writer exceptions (default writer may throw if Laravel DB unavailable).
     * No real DB; we only assert that calling info() does not rethrow.
     */
    public function test_does_not_throw_when_default_persistence_backend_is_unavailable(): void
    {
        $logger = new MongoDbLogger();

        $logger->info('test');
        $this->addToAssertionCount(1);
    }
}
