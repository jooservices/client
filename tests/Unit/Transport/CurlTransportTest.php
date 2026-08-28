<?php

declare(strict_types=1);

namespace JOOservices\Client\Tests\Unit\Transport;

use JOOservices\Client\Dto\RequestOptions;
use JOOservices\Client\Exceptions\RequestException;
use JOOservices\Client\Transport\CurlTransport;
use JOOservices\Client\Transport\Curl\CurlHeaderBuffer;
use JOOservices\Client\Transport\Curl\CurlSession;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CurlTransportTest extends TestCase
{
    #[Test]
    public function testRejectsNonHttpSchemes(): void
    {
        $factory = new Psr17Factory();
        $transport = new CurlTransport($factory, $factory, $factory);
        $request = $factory->createRequest('GET', 'file:///etc/passwd');

        $this->expectException(RequestException::class);
        $transport->handle($request, new RequestOptions());
    }

    #[Test]
    public function testHonorsAllPortableCapabilities(): void
    {
        $factory = new Psr17Factory();
        $caps = new CurlTransport($factory, $factory, $factory)->capabilities();
        self::assertTrue($caps->timeout);
        self::assertTrue($caps->connectTimeout);
        self::assertTrue($caps->proxy);
        self::assertTrue($caps->verifySsl);
        self::assertTrue($caps->allowRedirects);
    }

    #[Test]
    public function testSelectsSchemeSpecificProxy(): void
    {
        $factory = new Psr17Factory();
        $proxy = new \JOOservices\Client\Transport\Curl\CurlProxy();
        $https = $factory->createRequest('GET', 'https://abc.com/x');
        $http = $factory->createRequest('GET', 'http://abc.com/x');
        $options = new RequestOptions(proxy: ['http' => 'http://h-proxy:8080', 'https' => 'http://s-proxy:8080']);

        self::assertSame('http://s-proxy:8080', $proxy->resolve($https, $options));
        self::assertSame('http://h-proxy:8080', $proxy->resolve($http, $options));
    }

    #[Test]
    public function testProxyNoListMatchesApexAndSubdomain(): void
    {
        $factory = new Psr17Factory();
        $proxy = new \JOOservices\Client\Transport\Curl\CurlProxy();
        $options = new RequestOptions(proxy: ['https' => 'http://proxy:8080', 'no' => '.example.test']);

        self::assertNull($proxy->resolve($factory->createRequest('GET', 'https://example.test/x'), $options));
        self::assertNull($proxy->resolve($factory->createRequest('GET', 'https://api.example.test/x'), $options));
    }

    #[Test]
    public function testHeaderBufferAndSessionManageNativeHandles(): void
    {
        $headers = new CurlHeaderBuffer();
        $headers->append(null, "HTTP/1.1 201 Created\r\n");
        $headers->append(null, "X-Test: yes\r\n");
        self::assertSame(201, $headers->status);
        self::assertSame(['yes'], $headers->headers['X-Test']);

        // RFC 7230 obs-fold: a continuation line (leading space/tab) extends the previous header's
        // value instead of being parsed as its own "name: value" line.
        $folded = new CurlHeaderBuffer();
        $folded->append(null, "HTTP/1.1 200 OK\r\n");
        $folded->append(null, "X-Folded: first-part\r\n");
        $folded->append(null, " continuation-with-colon: still-the-same-value\r\n");
        self::assertSame(['first-part continuation-with-colon: still-the-same-value'], $folded->headers['X-Folded']);
        self::assertArrayNotHasKey('continuation-with-colon', $folded->headers);

        $session = new CurlSession();
        $session->handle();
        $session->share();
        $session->close();
        $session->handle();
        $session->close();
    }

    #[Test]
    public function testDiscardsHandlesInsteadOfReusingThemAcrossADetectedFork(): void
    {
        $session = new CurlSession();
        $handle = $session->handle();
        $share = $session->share();

        // Simulate having forked: same object, but getmypid() would now return a different value in
        // the child. Flip the tracked pid via reflection rather than actually pcntl_fork()ing (not
        // available in this environment, and inherently flaky as a unit test either way).
        $pidProperty = new \ReflectionProperty(CurlSession::class, 'pid');
        $currentPid = $pidProperty->getValue($session);
        self::assertIsInt($currentPid);
        $pidProperty->setValue($session, $currentPid - 1);

        self::assertNotSame($handle, $session->handle());
        self::assertNotSame($share, $session->share());
    }

    #[Test]
    public function testResolveEntriesBracketsIpv6AddressesForCurl(): void
    {
        $factory = new Psr17Factory();
        $exchange = new \JOOservices\Client\Transport\Curl\CurlExchange($factory, $factory);
        $method = new \ReflectionClass($exchange)->getMethod('resolveEntries');
        $https = $factory->createRequest('GET', 'https://example.test/x');
        $http = $factory->createRequest('GET', 'http://example.test/x');

        self::assertSame(
            [
                'example.test:443:[2001:db8::1]',
                'example.test:443:[2001:db8::2]',
                'example.test:443:192.0.2.1',
            ],
            $method->invoke($exchange, $https, ['2001:db8::1', '[2001:db8::2]', '192.0.2.1']),
        );
        self::assertSame(
            ['example.test:80:[::1]'],
            $method->invoke($exchange, $http, ['::1']),
        );
    }

    #[Test]
    public function testProxySupportsDirectValueAndNoProxyPatterns(): void
    {
        $factory = new Psr17Factory();
        $proxy = new \JOOservices\Client\Transport\Curl\CurlProxy();
        $request = $factory->createRequest('GET', 'https://api.test/x');
        self::assertSame('http://proxy.test', $proxy->resolve($request, new RequestOptions(proxy: 'http://proxy.test')));
        self::assertNull($proxy->resolve($request, new RequestOptions(proxy: ['no' => 'api.test', 'https' => 'http://proxy.test'])));
        self::assertNull($proxy->resolve($request, new RequestOptions(proxy: ['http' => 'http://proxy.test'])));
    }
}
