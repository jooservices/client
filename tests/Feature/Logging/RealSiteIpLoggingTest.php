<?php

declare(strict_types=1);

namespace Tests\Feature\Logging;

use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Logging\MongoDbLogger;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Real-network integration test (no MongoDB). Run manually with JOOCLIENT_RUN_LIVE_NETWORK_TESTS=1.
 * Uses in-memory writer only; asserts wan_ip/target_ip/local_ip/target_hostname on logged documents.
 */
#[Group('feature')]
#[Group('integration')]
#[Group('live-network')]
class RealSiteIpLoggingTest extends TestCase
{
    public function test_it_logs_ip_metadata_for_real_public_domains(): void
    {
        if (!getenv('JOOCLIENT_RUN_LIVE_NETWORK_TESTS')) {
            $this->markTestSkipped('Set JOOCLIENT_RUN_LIVE_NETWORK_TESTS=1 to run real-network tests.');
        }

        $documents = [];
        $logger = new MongoDbLogger(
            writer: function (array $document) use (&$documents): void {
                $documents[] = $document;
            }
        );

        $client = ClientBuilder::create()
            ->withLogger($logger)
            ->withTimeout(15)
            ->withConnectTimeout(10)
            ->build();

        $targets = [
            'https://httpbin.org/get',
            'https://example.com',
            'https://google.com',
        ];

        foreach ($targets as $target) {
            $client->get($target, [
                'allow_redirects' => true,
            ]);
        }

        foreach ($targets as $target) {
            $host = parse_url($target, PHP_URL_HOST);

            $requestDoc = null;
            $responseDoc = null;

            foreach ($documents as $document) {
                $message = (string) ($document['message'] ?? '');
                if ($requestDoc === null && str_contains($message, "Sending request to GET {$target}")) {
                    $requestDoc = $document;
                }

                if ($responseDoc === null && str_contains($message, "for GET {$target}")) {
                    $responseDoc = $document;
                }
            }

            $this->assertNotNull($requestDoc);
            $this->assertNotNull($responseDoc);

            $this->assertArrayHasKey('target_hostname', $requestDoc);
            $this->assertSame($host, $requestDoc['target_hostname']);
            $this->assertArrayHasKey('target_hostname', $responseDoc);
            $this->assertSame($host, $responseDoc['target_hostname']);
            $this->assertArrayHasKey('target_ip', $responseDoc);
            $this->assertArrayHasKey('local_ip', $responseDoc);
        }
    }
}
