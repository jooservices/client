<?php

declare(strict_types=1);

namespace JOOservices\Client\Tests\Unit\Support;

use JOOservices\Client\Dto\RequestOptions;
use JOOservices\Client\Exceptions\RequestException;
use JOOservices\Client\Support\RedirectHandler;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

require_once __DIR__ . '/../../Fixtures/redirect-target-dns-stub.php';

final class RedirectHandlerTest extends TestCase
{
    #[Test]
    public function testPassesTheVerifiedPublicAddressesThroughToSendForARedirectTarget(): void
    {
        $factory = new Psr17Factory();
        $handler = new RedirectHandler($factory, $factory);
        $seenPins = [];

        $handler->send(
            $factory->createRequest('GET', 'https://abc.com/from'),
            new RequestOptions(allowRedirects: true),
            function (RequestInterface $request, RequestOptions $options, ?array $pinnedAddresses) use (&$seenPins): Response {
                $seenPins[] = $pinnedAddresses;

                return $request->getUri()->getPath() === '/from' ? new Response(302, ['Location' => 'https://abc.com/to']) : new Response(200);
            },
        );

        // The very first (original, non-redirect) request is never pinned — RedirectTargetPolicy only
        // checks redirect targets. The second call, for the redirect target, must carry the addresses
        // the policy just verified as public.
        self::assertCount(2, $seenPins);
        self::assertNull($seenPins[0]);
        self::assertIsArray($seenPins[1]);
        self::assertNotEmpty($seenPins[1]);
        foreach ($seenPins[1] as $address) {
            self::assertNotFalse(filter_var($address, FILTER_VALIDATE_IP));
        }
    }

    #[Test]
    public function testThrowsInsteadOfSilentlyReturningTheLastRedirectWhenTheLimitIsExceeded(): void
    {
        $factory = new Psr17Factory();
        $handler = new RedirectHandler($factory, $factory);
        $calls = 0;

        $this->expectException(RequestException::class);
        $handler->send(
            $factory->createRequest('GET', 'https://abc.com/start'),
            new RequestOptions(allowRedirects: ['max' => 2]),
            function (RequestInterface $request) use (&$calls): Response {
                ++$calls;

                return new Response(302, ['Location' => 'https://abc.com/next-' . $calls]);
            },
        );
    }

    #[Test]
    public function testStripsAuthorizationOnCrossHostRedirect(): void
    {
        $factory = new Psr17Factory();
        $handler = new RedirectHandler($factory, $factory);
        $request = $factory->createRequest('GET', 'https://abc.com/from')
            ->withHeader('Authorization', 'Bearer secret')
            ->withHeader('Cookie', 'sid=1')
            ->withHeader('X-Api-Key', 'secret-key')
            ->withHeader('X-Auth-Token', 'secret-token')
            ->withHeader('X-Access-Token', 'access')
            ->withHeader('X-Secret', 'hidden');
        $seen = [];

        $response = $handler->send(
            $request,
            new RequestOptions(allowRedirects: true),
            function (RequestInterface $current) use (&$seen): Response {
                $seen[] = $current;
                if (count($seen) === 1) {
                    return new Response(302, ['Location' => 'https://example.com/to']);
                }

                return new Response(200);
            },
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Bearer secret', $seen[0]->getHeaderLine('Authorization'));
        self::assertSame('', $seen[1]->getHeaderLine('Authorization'));
        self::assertSame('', $seen[1]->getHeaderLine('Cookie'));
        self::assertSame('', $seen[1]->getHeaderLine('X-Api-Key'));
        self::assertSame('', $seen[1]->getHeaderLine('X-Auth-Token'));
        self::assertSame('', $seen[1]->getHeaderLine('X-Access-Token'));
        self::assertSame('', $seen[1]->getHeaderLine('X-Secret'));
        self::assertSame('example.com', $seen[1]->getUri()->getHost());
    }

    #[Test]
    public function testRejectsPublicToPrivateRedirectByDefault(): void
    {
        $factory = new Psr17Factory();
        $handler = new RedirectHandler($factory, $factory);

        $this->expectException(\JOOservices\Client\Exceptions\RequestException::class);
        $handler->send(
            $factory->createRequest('GET', 'https://public.example/from'),
            new RequestOptions(allowRedirects: true),
            fn(): Response => new Response(302, ['Location' => 'http://169.254.169.254/latest/meta-data']),
        );
    }

    #[Test]
    public function testAllowsPrivateRedirectWhenExplicitlyConfigured(): void
    {
        $factory = new Psr17Factory();
        $handler = new RedirectHandler($factory, $factory);
        $calls = 0;
        $response = $handler->send(
            $factory->createRequest('GET', 'https://public.example/from'),
            new RequestOptions(allowRedirects: ['allow_private' => true]),
            function () use (&$calls): Response {
                ++$calls;

                return $calls === 1
                    ? new Response(302, ['Location' => 'http://127.0.0.1/internal'])
                    : new Response(200);
            },
        );

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function testKeepsAuthorizationOnSameHostRedirect(): void
    {
        $factory = new Psr17Factory();
        $handler = new RedirectHandler($factory, $factory);
        $request = $factory->createRequest('GET', 'https://abc.com/from')->withHeader('Authorization', 'Bearer secret');
        $seen = [];

        $handler->send(
            $request,
            new RequestOptions(allowRedirects: true),
            function (RequestInterface $current) use (&$seen): Response {
                $seen[] = $current;
                if (count($seen) === 1) {
                    return new Response(302, ['Location' => '/to']);
                }

                return new Response(200);
            },
        );

        self::assertSame('Bearer secret', $seen[1]->getHeaderLine('Authorization'));
        self::assertSame('https://abc.com/to', (string) $seen[1]->getUri());
    }

    #[Test]
    public function testDoesNotFollowWhenDisabled(): void
    {
        $factory = new Psr17Factory();
        $handler = new RedirectHandler($factory, $factory);
        $calls = 0;
        $response = $handler->send(
            $factory->createRequest('GET', 'https://abc.com/from'),
            new RequestOptions(allowRedirects: false),
            function () use (&$calls): Response {
                ++$calls;

                return new Response(301, ['Location' => 'https://abc.com/to']);
            },
        );

        self::assertSame(1, $calls);
        self::assertSame(301, $response->getStatusCode());
    }

    #[Test]
    public function testReturns301WithoutLocation(): void
    {
        $factory = new Psr17Factory();
        $handler = new RedirectHandler($factory, $factory);
        $response = $handler->send(
            $factory->createRequest('GET', 'https://abc.com/from'),
            new RequestOptions(allowRedirects: true),
            fn(): Response => new Response(301),
        );

        self::assertSame(301, $response->getStatusCode());
    }

    #[Test]
    public function testUnresolvedRedirectHostIsRejected(): void
    {
        $factory = new Psr17Factory();
        $handler = new RedirectHandler($factory, $factory);

        $this->expectException(\JOOservices\Client\Exceptions\RequestException::class);
        $handler->send(
            $factory->createRequest('GET', 'https://example.com/from'),
            new RequestOptions(allowRedirects: true),
            fn(): Response => new Response(302, ['Location' => 'https://no-such-host-ssrf-test.invalid/internal']),
        );
    }
}
