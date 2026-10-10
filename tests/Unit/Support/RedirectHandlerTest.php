<?php

declare(strict_types=1);

namespace JOOservices\Client\Tests\Unit\Support;

use Faker\Factory;
use JOOservices\Client\Dto\RequestOptions;
use JOOservices\Client\Exceptions\RequestException;
use JOOservices\Client\Exceptions\TimeoutException;
use JOOservices\Client\Support\RedirectHandler;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
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

                return $request->getUri()->getPath() === '/from' ? new Response(302, ['Location' => 'https://example.com/to']) : new Response(200);
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
    public function testAllowsPrivateDnsRedirectWhenExplicitlyConfigured(): void
    {
        $factory = new Psr17Factory();
        $handler = new RedirectHandler($factory, $factory);
        $calls = 0;

        $response = $handler->send(
            $factory->createRequest('GET', 'https://abc.com/from'),
            new RequestOptions(allowRedirects: ['allow_private' => true]),
            function () use (&$calls): Response {
                ++$calls;

                return $calls === 1
                    ? new Response(302, ['Location' => 'https://private.example/to'])
                    : new Response(200);
            },
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(2, $calls);
    }

    #[Test]
    #[DataProvider('nonGlobalLiteralRedirectAddresses')]
    public function testRejectsNonGlobalLiteralRedirectAddresses(string $address): void
    {
        $this->assertRedirectTargetIsRejected('https://' . $address . '/to');
    }

    #[Test]
    #[DataProvider('nonGlobalDnsRedirectTargets')]
    public function testRejectsNonGlobalDnsRedirectResults(string $hostname): void
    {
        $this->assertRedirectTargetIsRejected('https://' . $hostname . '/to');
    }

    #[Test]
    public function testAllowsNat64LiteralWhenEmbeddedIpv4IsPublic(): void
    {
        $faker = Factory::create();
        $faker->seed(20261011);
        $embeddedAddress = sprintf('8.8.%d.%d', $faker->numberBetween(0, 255), $faker->numberBetween(0, 255));
        $location = $this->nat64Target($embeddedAddress);
        $factory = new Psr17Factory();
        $handler = new RedirectHandler($factory, $factory);
        $calls = 0;

        $response = $handler->send(
            $factory->createRequest('GET', 'https://abc.com/from'),
            new RequestOptions(allowRedirects: true),
            function () use (&$calls, $location): Response {
                ++$calls;

                return $calls === 1 ? new Response(302, ['Location' => $location]) : new Response(200);
            },
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(2, $calls);
    }

    #[Test]
    public function testRejectsNat64PrivateAndLoopbackIpv4Literals(): void
    {
        $faker = Factory::create();
        $faker->seed(20261012);
        $addresses = [
            sprintf('10.%d.%d.%d', $faker->numberBetween(0, 255), $faker->numberBetween(0, 255), $faker->numberBetween(1, 254)),
            sprintf('127.%d.%d.%d', $faker->numberBetween(0, 255), $faker->numberBetween(0, 255), $faker->numberBetween(1, 254)),
        ];

        foreach ($addresses as $address) {
            $this->assertRedirectTargetIsRejected($this->nat64Target($address));
        }
    }

    #[Test]
    public function testRejectsNat64LocalUseTranslationPrefix(): void
    {
        $faker = Factory::create();
        $faker->seed(20261013);
        $suffix = $faker->numberBetween(1, 65_535);

        $this->assertRedirectTargetIsRejected(sprintf('https://[64:ff9b:1::%x]/to', $suffix));
    }

    #[Test]
    public function testRejectsNonGlobalIanaSpecialPurposeLiterals(): void
    {
        $faker = Factory::create();
        $faker->seed(20261014);
        $targets = [
            'https://192.88.99.2/to',
            'https://[100::1]/to',
            'https://[100:0:0:1::1]/to',
            sprintf('https://[100:0:0:1::%x]/to', $faker->numberBetween(1, 65_535)),
            'https://[2001:2::1]/to',
            sprintf('https://[2001:2::%x]/to', $faker->numberBetween(1, 65_535)),
            'https://[2001:10::1]/to',
            sprintf('https://[2001:10::%x]/to', $faker->numberBetween(1, 65_535)),
            'https://[2001::1]/to',
            sprintf('https://[2001:8::%x]/to', $faker->numberBetween(1, 65_535)),
            'https://[5f00::1]/to',
            sprintf('https://[5f00::%x]/to', $faker->numberBetween(1, 65_535)),
        ];

        foreach ($targets as $target) {
            $this->assertRedirectTargetIsRejected($target);
        }
    }

    #[Test]
    #[DataProvider('globallyReachable192000Exceptions')]
    public function testAllowsGloballyReachable192000Exceptions(string $target): void
    {
        $factory = new Psr17Factory();
        $handler = new RedirectHandler($factory, $factory);
        $calls = 0;

        $response = $handler->send(
            $factory->createRequest('GET', 'https://abc.com/from'),
            new RequestOptions(allowRedirects: true),
            function () use (&$calls, $target): Response {
                ++$calls;

                return $calls === 1 ? new Response(302, ['Location' => $target]) : new Response(200);
            },
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(2, $calls);
    }

    #[Test]
    public function testAllowsGloballyReachableIanaSpecialPurposeAllocations(): void
    {
        $faker = Factory::create();
        $faker->seed(20261015);
        $ipv4Suffix = $faker->numberBetween(1, 254);
        $ipv6Suffix = $faker->numberBetween(1, 65_535);
        $targets = [
            'https://[2001:1::1]/to',
            'https://[2001:1::2]/to',
            'https://[2001:1::3]/to',
            sprintf('https://[2001:3:%x::1]/to', $ipv6Suffix),
            sprintf('https://[2001:4:112:%x::1]/to', $ipv6Suffix),
            sprintf('https://[2001:%x::1]/to', $faker->numberBetween(0x20, 0x2f)),
            sprintf('https://[2001:%x::1]/to', $faker->numberBetween(0x30, 0x3f)),
            sprintf('https://[2620:4f:8000:%x::1]/to', $ipv6Suffix),
            sprintf('https://192.31.196.%d/to', $ipv4Suffix),
            sprintf('https://192.52.193.%d/to', $ipv4Suffix),
            sprintf('https://192.175.48.%d/to', $ipv4Suffix),
        ];

        foreach ($targets as $target) {
            $this->assertRedirectTargetIsAllowed($target);
        }
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

    #[Test]
    public function testFollowsSameHostRedirectEvenWhenTheHostDoesNotResolveInDns(): void
    {
        $factory = new Psr17Factory();
        $handler = new RedirectHandler($factory, $factory);
        $seen = [];

        $response = $handler->send(
            $factory->createRequest('GET', 'https://hosts-only.internal/from'),
            new RequestOptions(allowRedirects: true),
            function (RequestInterface $current) use (&$seen): Response {
                $seen[] = $current;

                return count($seen) === 1 ? new Response(302, ['Location' => '/to']) : new Response(200);
            },
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('https://hosts-only.internal/to', (string) $seen[1]->getUri());
    }

    #[Test]
    public function testReplaysCookiesSetOnAnEarlierHopToTheNextSameHostHop(): void
    {
        $factory = new Psr17Factory();
        $handler = new RedirectHandler($factory, $factory);
        $seen = [];

        $handler->send(
            $factory->createRequest('GET', 'https://abc.com/login'),
            new RequestOptions(allowRedirects: true),
            function (RequestInterface $current) use (&$seen): Response {
                $seen[] = $current;

                return count($seen) === 1
                    ? new Response(302, ['Location' => '/home', 'Set-Cookie' => 'sid=abc; Path=/; Secure'])
                    : new Response(200);
            },
        );

        self::assertSame('sid=abc', $seen[1]->getHeaderLine('Cookie'));
    }

    #[Test]
    public function testDoesNotReplayCookiesAcrossHosts(): void
    {
        $factory = new Psr17Factory();
        $handler = new RedirectHandler($factory, $factory);
        $seen = [];

        $handler->send(
            $factory->createRequest('GET', 'https://abc.com/login'),
            new RequestOptions(allowRedirects: true),
            function (RequestInterface $current) use (&$seen): Response {
                $seen[] = $current;

                return count($seen) === 1
                    ? new Response(302, ['Location' => 'https://example.com/home', 'Set-Cookie' => 'sid=abc; Path=/'])
                    : new Response(200);
            },
        );

        self::assertSame('', $seen[1]->getHeaderLine('Cookie'));
    }

    #[Test]
    public function testDoesNotReplayMultiLabelPublicSuffixCookieAcrossHosts(): void
    {
        $factory = new Psr17Factory();
        $handler = new RedirectHandler($factory, $factory);
        $seen = [];

        $handler->send(
            $factory->createRequest('GET', 'https://attacker.co.uk/login'),
            new RequestOptions(allowRedirects: true),
            function (RequestInterface $current) use (&$seen): Response {
                $seen[] = $current;

                return count($seen) === 1
                    ? new Response(302, ['Location' => 'https://victim.co.uk/home', 'Set-Cookie' => 'sid=abc; Domain=co.uk; Path=/'])
                    : new Response(200);
            },
        );

        self::assertCount(2, $seen);
        self::assertSame('', $seen[1]->getHeaderLine('Cookie'));
    }

    #[Test]
    public function testDoesNotReplayCookiesWhenDisabled(): void
    {
        $factory = new Psr17Factory();
        $handler = new RedirectHandler($factory, $factory);
        $seen = [];

        $handler->send(
            $factory->createRequest('GET', 'https://abc.com/login'),
            new RequestOptions(allowRedirects: ['cookies' => false]),
            function (RequestInterface $current) use (&$seen): Response {
                $seen[] = $current;

                return count($seen) === 1
                    ? new Response(302, ['Location' => '/home', 'Set-Cookie' => 'sid=abc; Path=/'])
                    : new Response(200);
            },
        );

        self::assertSame('', $seen[1]->getHeaderLine('Cookie'));
    }

    #[Test]
    public function testKeepsCookiesWhenUpgradingToHttpsOnTheSameHost(): void
    {
        $factory = new Psr17Factory();
        $handler = new RedirectHandler($factory, $factory);
        $seen = [];

        $handler->send(
            $factory->createRequest('GET', 'http://abc.com/from')->withHeader('Cookie', 'sid=1'),
            new RequestOptions(allowRedirects: true),
            function (RequestInterface $current) use (&$seen): Response {
                $seen[] = $current;

                return count($seen) === 1 ? new Response(302, ['Location' => 'https://abc.com/to']) : new Response(200);
            },
        );

        self::assertSame('sid=1', $seen[1]->getHeaderLine('Cookie'));
    }

    #[Test]
    public function testStripsCookiesWhenDowngradingToPlainHttp(): void
    {
        $factory = new Psr17Factory();
        $handler = new RedirectHandler($factory, $factory);
        $seen = [];

        $handler->send(
            $factory->createRequest('GET', 'https://abc.com/from')->withHeader('Cookie', 'sid=1'),
            new RequestOptions(allowRedirects: true),
            function (RequestInterface $current) use (&$seen): Response {
                $seen[] = $current;

                return count($seen) === 1 ? new Response(302, ['Location' => 'http://abc.com/to']) : new Response(200);
            },
        );

        self::assertSame('', $seen[1]->getHeaderLine('Cookie'));
    }

    #[Test]
    public function testKeepsCookiesWhenOnlyThePortChanges(): void
    {
        $factory = new Psr17Factory();
        $handler = new RedirectHandler($factory, $factory);
        $seen = [];

        $handler->send(
            $factory->createRequest('GET', 'https://abc.com/from')->withHeader('Cookie', 'sid=1'),
            new RequestOptions(allowRedirects: true),
            function (RequestInterface $current) use (&$seen): Response {
                $seen[] = $current;

                return count($seen) === 1 ? new Response(302, ['Location' => 'https://abc.com:8443/to']) : new Response(200);
            },
        );

        self::assertSame('sid=1', $seen[1]->getHeaderLine('Cookie'));
        self::assertSame(8443, $seen[1]->getUri()->getPort());
    }

    #[Test]
    public function testDoesNotReplayJarCookiesOnADowngradeToPlainHttp(): void
    {
        $factory = new Psr17Factory();
        $handler = new RedirectHandler($factory, $factory);
        $seen = [];

        $handler->send(
            $factory->createRequest('GET', 'https://abc.com/login'),
            new RequestOptions(allowRedirects: true),
            function (RequestInterface $current) use (&$seen): Response {
                $seen[] = $current;

                return count($seen) === 1
                    ? new Response(302, ['Location' => 'http://abc.com/home', 'Set-Cookie' => 'sid=abc; Path=/'])
                    : new Response(200);
            },
        );

        self::assertSame('', $seen[1]->getHeaderLine('Cookie'));
    }

    #[Test]
    public function testTracksTheEffectiveUriWhenRequested(): void
    {
        $factory = new Psr17Factory();
        $handler = new RedirectHandler($factory, $factory);
        $calls = 0;

        $response = $handler->send(
            $factory->createRequest('GET', 'https://abc.com/from'),
            new RequestOptions(allowRedirects: ['track_redirects' => true]),
            function () use (&$calls): Response {
                ++$calls;

                return $calls === 1 ? new Response(302, ['Location' => 'https://example.com/to']) : new Response(200);
            },
        );

        self::assertSame('https://example.com/to', $response->getHeaderLine('X-Joo-Effective-Uri'));
        self::assertSame('https://example.com/to', $response->getHeaderLine('X-Joo-Redirect-History'));
    }

    #[Test]
    public function testSharesTheTotalTimeoutBudgetAcrossHops(): void
    {
        $factory = new Psr17Factory();
        $handler = new RedirectHandler($factory, $factory);
        $timeouts = [];

        $handler->send(
            $factory->createRequest('GET', 'https://abc.com/from'),
            new RequestOptions(timeout: 30.0, allowRedirects: ['total_timeout' => 2.0]),
            function (RequestInterface $current, RequestOptions $options) use (&$timeouts): Response {
                $timeouts[] = $options->timeout;

                return count($timeouts) === 1 ? new Response(302, ['Location' => '/to']) : new Response(200);
            },
        );

        self::assertNotNull($timeouts[0]);
        self::assertLessThanOrEqual(2.0, $timeouts[0]);
        self::assertNotNull($timeouts[1]);
        self::assertLessThanOrEqual(2.0, $timeouts[1]);
    }

    #[Test]
    public function testThrowsWhenTheTotalTimeoutBudgetIsExhausted(): void
    {
        $factory = new Psr17Factory();
        $handler = new RedirectHandler($factory, $factory);

        $this->expectException(TimeoutException::class);
        $handler->send(
            $factory->createRequest('GET', 'https://abc.com/from'),
            new RequestOptions(allowRedirects: ['total_timeout' => 0.001]),
            function (): Response {
                usleep(20_000);

                return new Response(302, ['Location' => '/to']);
            },
        );
    }

    /** @return iterable<string, array{string}> */
    public static function nonGlobalLiteralRedirectAddresses(): iterable
    {
        yield 'CGNAT start' => ['100.64.0.1'];
        yield 'CGNAT end' => ['100.127.255.254'];
        yield '192.0.0.0/24 non-exception' => ['192.0.0.8'];
        yield 'benchmark start' => ['198.18.0.1'];
        yield 'benchmark end' => ['198.19.255.254'];
        yield 'NAT64 start' => ['[64:ff9b::1]'];
        yield 'NAT64 end' => ['[64:ff9b::ffff:ffff]'];
        yield '6to4 start' => ['[2002:c000:0201::1]'];
        yield '6to4 end' => ['[2002:ffff:ffff::1]'];
    }

    /** @return iterable<string, array{string}> */
    public static function nonGlobalDnsRedirectTargets(): iterable
    {
        yield 'CGNAT' => ['cgnat.example'];
        yield '192.0.0.0/24 non-exception' => ['protocol-assignment.example'];
        yield 'benchmark' => ['benchmark.example'];
        yield 'NAT64' => ['nat64.example'];
        yield '6to4' => ['6to4.example'];
    }

    /** @return iterable<string, array{string}> */
    public static function globallyReachable192000Exceptions(): iterable
    {
        yield 'literal .9' => ['https://192.0.0.9/to'];
        yield 'literal .10' => ['https://192.0.0.10/to'];
        yield 'DNS .9' => ['https://global-192-0-0-9.example/to'];
        yield 'DNS .10' => ['https://global-192-0-0-10.example/to'];
    }

    private function assertRedirectTargetIsRejected(string $location): void
    {
        $factory = new Psr17Factory();
        $handler = new RedirectHandler($factory, $factory);
        $calls = 0;

        try {
            $handler->send(
                $factory->createRequest('GET', 'https://abc.com/from'),
                new RequestOptions(allowRedirects: true),
                function () use (&$calls, $location): Response {
                    ++$calls;

                    return $calls === 1 ? new Response(302, ['Location' => $location]) : new Response(200);
                },
            );
            self::fail('Expected a non-global redirect target to be rejected.');
        } catch (RequestException $exception) {
            self::assertStringContainsString('private, link-local, or unresolved', $exception->getMessage());
        }

        self::assertSame(1, $calls, 'A rejected target must not be sent as the next hop.');
    }

    private function assertRedirectTargetIsAllowed(string $location): void
    {
        $factory = new Psr17Factory();
        $handler = new RedirectHandler($factory, $factory);
        $calls = 0;

        $response = $handler->send(
            $factory->createRequest('GET', 'https://abc.com/from'),
            new RequestOptions(allowRedirects: true),
            function () use (&$calls, $location): Response {
                ++$calls;

                return $calls === 1 ? new Response(302, ['Location' => $location]) : new Response(200);
            },
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(2, $calls);
    }

    private function nat64Target(string $embeddedAddress): string
    {
        $prefixBytes = inet_pton('64:ff9b::');
        $ipv4Bytes = inet_pton($embeddedAddress);
        if ($prefixBytes === false || $ipv4Bytes === false || strlen($ipv4Bytes) !== 4) {
            throw new \InvalidArgumentException('A valid IPv4 address is required for the NAT64 test target.');
        }

        $target = inet_ntop(substr($prefixBytes, 0, 12) . $ipv4Bytes);
        if ($target === false) {
            throw new \InvalidArgumentException('The NAT64 test target could not be rendered as IPv6.');
        }

        return 'https://[' . $target . ']/to';
    }
}
