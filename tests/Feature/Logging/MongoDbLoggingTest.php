<?php

declare(strict_types=1);

namespace Tests\Feature\Logging;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Logging\MongoDbLogger;
use JOOservices\Client\Models\Mongo\ClientRequestLog;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('feature')]
class MongoDbLoggingTest extends TestCase
{
    /**
     * Uses in-memory writer (no database). Mock HTTP. Asserts document shape:
     * method, uri, target_hostname, response_payload, payload_truncated.
     * Does not assert IP fields (wan_ip, target_ip, local_ip) as mock does not set transfer stats.
     */
    public function test_it_maps_payloads_through_ClientBuilder_LoggingMiddleware_MongoDbLogger(): void
    {
        $documents = [];

        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], str_repeat('X', 20)),
        ]);

        $handler = HandlerStack::create($mock);

        $logger = new MongoDbLogger(
            maxRequestBodyBytes: 5,
            maxResponseBodyBytes: 8,
            writer: function (array $document) use (&$documents): void {
                $documents[] = $document;
            }
        );

        $client = ClientBuilder::create()
            ->withOption('handler', $handler)
            ->withLogger($logger, logBodies: true)
            ->build();

        $client->post('https://example.com/mongo-e2e', [
            'body' => 'request-payload-abcdef',
        ]);

        $this->assertCount(4, $documents);

        $requestLine = null;
        $responseLine = null;
        $responseBody = null;

        foreach ($documents as $document) {
            if (($document['message'] ?? '') === 'Sending request to POST https://example.com/mongo-e2e') {
                $requestLine = $document;
            }

            if (str_contains((string) ($document['message'] ?? ''), 'Received response 200 for POST')) {
                $responseLine = $document;
            }

            if (isset($document['response_payload'])) {
                $responseBody = $document;
            }
        }

        $this->assertNotNull($requestLine);
        $this->assertNotNull($responseLine);
        $this->assertNotNull($responseBody);

        $this->assertArrayHasKey('method', $requestLine);
        $this->assertSame('POST', $requestLine['method']);
        $this->assertArrayHasKey('uri', $requestLine);
        $this->assertSame('https://example.com/mongo-e2e', $requestLine['uri']);
        $this->assertArrayHasKey('target_hostname', $requestLine);
        $this->assertSame('example.com', $requestLine['target_hostname']);

        $this->assertArrayHasKey('status', $responseLine);
        $this->assertSame(200, $responseLine['status']);
        $this->assertArrayHasKey('duration_ms', $responseLine);

        $this->assertArrayHasKey('response_payload', $responseBody);
        $this->assertSame('XXXXXXXX', $responseBody['response_payload']);
        $this->assertArrayHasKey('payload_truncated', $responseBody);
        $this->assertTrue($responseBody['payload_truncated']);
    }

    /**
     * Uses default connection and collection via Laravel/Eloquent only (config from env).
     * Persistence and verification via ClientRequestLog; data is written to real MongoDB.
     * Skips when MongoDB is not reachable (e.g. not running).
     */
    #[Group('integration')]
    public function test_it_persists_MongoDbLogger_output_to_real_MongoDB_when_available(): void
    {
        try {
            \Illuminate\Support\Facades\DB::connection('mongodb')->getMongoDB()->command(['ping' => 1]);
        } catch (\Throwable $e) {
            $this->markTestSkipped('MongoDB not available (check MONGODB_URI / MONGODB_DATABASE): ' . $e->getMessage());
        }

        $marker = 'mongo-default-' . bin2hex(random_bytes(6));

        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], str_repeat('Y', 24)),
        ]);
        $handler = HandlerStack::create($mock);

        $logger = new MongoDbLogger(maxResponseBodyBytes: 10);

        $client = ClientBuilder::create()
            ->withOption('handler', $handler)
            ->withLogger($logger, logBodies: true)
            ->build();

        $client->get("https://example.com/{$marker}");

        $requestDoc = ClientRequestLog::where('message', 'like', '%' . $marker . '%')->orderBy('_id', 'desc')->first();
        $this->assertNotNull($requestDoc, 'Expected a request log in client_request_logs.');

        $responseBodyDoc = ClientRequestLog::where('response_payload', 'YYYYYYYYYY')->orderBy('_id', 'desc')->first();
        $this->assertNotNull($responseBodyDoc, 'Expected a response body log in client_request_logs.');
    }
}
