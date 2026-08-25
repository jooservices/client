<?php

declare(strict_types=1);

namespace JOOservices\Client\Tests\Unit\Testing;

use JOOservices\Client\Dto\RequestOptions;
use JOOservices\Client\Testing\InteractsWithHttpClient;
use JOOservices\Client\Testing\FakeHttpClient;
use JOOservices\Client\Testing\TestResponse;
use JOOservices\Client\Testing\TestResponseSequence;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HttpFakeRegistryTest extends TestCase
{
    use InteractsWithHttpClient;

    protected function setUp(): void
    {
        $this->setUpHttpFakes();
    }

    protected function tearDown(): void
    {
        $this->tearDownHttpFakes();
    }

    #[Test]
    public function testMatchesPatternAndRecordsRequests(): void
    {
        $sequence = (new TestResponseSequence())->push(TestResponse::make(201));
        $this->httpFakes()->respond('GET', 'https://api.test/users/*', $sequence);
        $response = $this->httpFakes()->handle((new Psr17Factory())->createRequest('GET', 'https://api.test/users/1'), new RequestOptions());

        self::assertSame(201, $response->getStatusCode());
        self::assertCount(1, $this->httpFakes()->recorded());
    }

    #[Test]
    public function testPathOnlyPatternsMatchRegardlessOfHost(): void
    {
        $sequence = (new TestResponseSequence())->push(TestResponse::make(201));
        $this->httpFakes()->respond('GET', '/users/*', $sequence);
        $response = $this->httpFakes()->handle((new Psr17Factory())->createRequest('GET', 'https://api.test/users/1'), new RequestOptions());

        self::assertSame(201, $response->getStatusCode());
    }

    #[Test]
    public function testQueryStringMatchingIsOrderIndependent(): void
    {
        $sequence = (new TestResponseSequence())->push(TestResponse::make(201));
        $this->httpFakes()->respond('GET', 'https://api.test/users?a=1&b=2', $sequence);
        $response = $this->httpFakes()->handle(
            (new Psr17Factory())->createRequest('GET', 'https://api.test/users?b=2&a=1'),
            new RequestOptions(),
        );

        self::assertSame(201, $response->getStatusCode());
    }

    #[Test]
    public function testFakeClientHandlesFallbackClearAndSequences(): void
    {
        $this->httpFakes()->preventStrayRequests(false);
        $request = (new Psr17Factory())->createRequest('POST', 'https://api.test/missing');
        self::assertSame(404, (new FakeHttpClient($this->httpFakes()))->sendRequest($request)->getStatusCode());

        $sequence = new TestResponseSequence();
        $sequence->push(TestResponse::json(['ok' => true]));
        $this->httpFakes()->respond('*', 'https://api.test/*', $sequence);
        self::assertSame('{"ok":true}', (string) $this->httpFakes()->handle((new Psr17Factory())->createRequest('GET', 'https://api.test/a'), new RequestOptions())->getBody());
        self::assertTrue($sequence->isEmpty());
        $this->httpFakes()->clear();
        self::assertSame([], $this->httpFakes()->recorded());
    }

    #[Test]
    public function testEmptySequenceRaisesReadableError(): void
    {
        $this->expectException(\RuntimeException::class);
        (new TestResponseSequence())->next();
    }

    #[Test]
    public function testTearDownHttpFakesClearsTheGlobalClientBuilderFakeState(): void
    {
        \JOOservices\Client\Client\ClientBuilder::fake();
        self::assertTrue(\JOOservices\Client\Client\ClientBuilder::isFaked());

        $this->tearDownHttpFakes();

        self::assertFalse(\JOOservices\Client\Client\ClientBuilder::isFaked());
    }
}
