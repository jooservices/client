<?php

declare(strict_types=1);

namespace JOOservices\Client\Tests\Arch;

use JOOservices\Client\Client\HttpClient;
use JOOservices\Client\Contracts\MiddlewareInterface;
use JOOservices\Client\Dto\ClientConfig;
use JOOservices\Client\Dto\RequestOptions;
use JOOservices\Client\Exceptions\NetworkConnectionException;
use JOOservices\Client\Exceptions\TimeoutException;
use JOOservices\Client\Response\Response;
use JOOservices\Client\Transport\PsrTransport;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use ReflectionClass;

final class ArchitectureTest extends TestCase
{
    #[Test]
    public function testHttpClientImplementsPsr18(): void
    {
        $interfaces = class_implements(HttpClient::class);
        self::assertIsArray($interfaces);
        self::assertArrayHasKey(ClientInterface::class, $interfaces);
        $method = new ReflectionClass(HttpClient::class)->getMethod('sendRequest');
        self::assertCount(1, $method->getParameters());
        self::assertSame('Psr\\Http\\Message\\RequestInterface', (string) $method->getParameters()[0]->getType());
        self::assertSame('Psr\\Http\\Message\\ResponseInterface', (string) $method->getReturnType());
    }

    #[Test]
    public function testNoJooPrefixedClassNames(): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/src'));
        foreach ($files as $file) {
            if (! $file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $name = $file->getBasename('.php');
            self::assertDoesNotMatchRegularExpression('/^(JOO|Joo)/', $name, $name);
        }
    }

    #[Test]
    public function testOutboundMiddlewareIsNotPsr15(): void
    {
        $process = new ReflectionClass(MiddlewareInterface::class)->getMethod('process');
        self::assertSame('Psr\\Http\\Message\\RequestInterface', (string) $process->getParameters()[0]->getType());
        self::assertFalse(interface_exists('Psr\\Http\\Server\\MiddlewareInterface')
            && is_a(MiddlewareInterface::class, 'Psr\\Http\\Server\\MiddlewareInterface', true));
    }

    #[Test]
    public function testTimeoutExtendsNonFinalNetworkException(): void
    {
        self::assertFalse(new ReflectionClass(NetworkConnectionException::class)->isFinal());
        self::assertFalse(new ReflectionClass(TimeoutException::class)->isAbstract());
    }

    #[Test]
    public function testRequestOptionsDefaultsAreUnset(): void
    {
        $options = new RequestOptions();
        self::assertNull($options->timeout);
        self::assertNull($options->verifySsl);
        self::assertNull($options->allowRedirects);
        self::assertSame(30.0, new ClientConfig()->timeout);
        self::assertTrue(new ClientConfig()->verifySsl);
    }

    #[Test]
    public function testPsrTransportHasNoPortableCaps(): void
    {
        $inner = new class implements ClientInterface {
            public function sendRequest(\Psr\Http\Message\RequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                return new \Nyholm\Psr7\Response();
            }
        };
        $caps = new PsrTransport($inner)->capabilities();
        self::assertFalse($caps->timeout);
        self::assertFalse($caps->proxy);
        self::assertFalse($caps->verifySsl);
        self::assertFalse($caps->allowRedirects);
    }

    #[Test]
    public function testResponseWrapperIsNotPsr7ResponseInterface(): void
    {
        $interfaces = class_implements(Response::class);
        self::assertIsArray($interfaces);
        self::assertArrayNotHasKey(\Psr\Http\Message\ResponseInterface::class, $interfaces);
        self::assertSame('Response', new ReflectionClass(Response::class)->getShortName());
    }
}
