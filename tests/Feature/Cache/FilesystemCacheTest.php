<?php

declare(strict_types=1);

namespace Tests\Feature\Cache;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use JOOservices\Client\Cache\FilesystemCache;
use JOOservices\Client\Client\ClientBuilder;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('feature')]
class FilesystemCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->clearTmpDir();
    }

    public function test_it_caches_responses_to_disk_and_serves_valid_cache_on_hit(): void
    {
        $mock = new MockHandler([
            new Response(200, [], '{"data": "fresh"}'),
            new Response(200, [], '{"data": "fresh_2"}'),
        ]);

        $handler = HandlerStack::create($mock);
        $cacheDir = $this->makeTmpDir('cache');

        $cache = new FilesystemCache($cacheDir);
        $client = ClientBuilder::create()
            ->withOption('handler', $handler)
            ->withCache($cache, defaultTtl: 3600)
            ->build();

        $response1 = $client->get('/api/data');
        $this->assertSame('{"data": "fresh"}', (string) $response1->toPsrResponse()->getBody());

        $files = scandir($cacheDir);
        $cachedFiles = array_diff($files, ['.', '..']);
        $this->assertNotEmpty($cachedFiles);

        $response2 = $client->get('/api/data');
        $this->assertSame('{"data": "fresh"}', (string) $response2->toPsrResponse()->getBody());
    }

    public function test_it_respects_TTL_and_expires_cache(): void
    {
        $mock = new MockHandler([
            new Response(200, [], '{"data": "v1"}'),
            new Response(200, [], '{"data": "v2"}'),
        ]);
        $handler = HandlerStack::create($mock);
        $cacheDir = $this->makeTmpDir('cache_ttl');

        $cache = new FilesystemCache($cacheDir);
        $client = ClientBuilder::create()
            ->withOption('handler', $handler)
            ->withCache($cache, defaultTtl: 1)
            ->build();

        $client->get('/api/expire');

        sleep(2);

        $response = $client->get('/api/expire');
        $this->assertSame('{"data": "v2"}', (string) $response->toPsrResponse()->getBody());
    }
}
