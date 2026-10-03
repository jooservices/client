<?php

declare(strict_types=1);

namespace JOOservices\Client\Tests\Unit\Support;

use JOOservices\Client\Support\CookieJar;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\Uri;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CookieJarTest extends TestCase
{
    #[Test]
    public function testReplaysAMatchingCookie(): void
    {
        $jar = new CookieJar();
        $jar->storeFromResponse(new Uri('https://abc.com/login'), new Response(200, ['Set-Cookie' => 'sid=abc; Path=/']));

        self::assertSame('sid=abc', $jar->headerFor(new Uri('https://abc.com/home')));
    }

    #[Test]
    public function testDoesNotReplayAHostOnlyCookieToASubdomain(): void
    {
        $jar = new CookieJar();
        $jar->storeFromResponse(new Uri('https://abc.com/login'), new Response(200, ['Set-Cookie' => 'sid=abc; Path=/']));

        self::assertNull($jar->headerFor(new Uri('https://sub.abc.com/home')));
    }

    #[Test]
    public function testDomainCookieMatchesSubdomains(): void
    {
        $jar = new CookieJar();
        $jar->storeFromResponse(new Uri('https://abc.com/login'), new Response(200, ['Set-Cookie' => 'sid=abc; Domain=abc.com; Path=/']));

        self::assertSame('sid=abc', $jar->headerFor(new Uri('https://sub.abc.com/home')));
    }

    #[Test]
    public function testRejectsACookieForAnUnrelatedDomain(): void
    {
        $jar = new CookieJar();
        $jar->storeFromResponse(new Uri('https://abc.com/login'), new Response(200, ['Set-Cookie' => 'sid=abc; Domain=other.com; Path=/']));

        self::assertNull($jar->headerFor(new Uri('https://abc.com/home')));
        self::assertNull($jar->headerFor(new Uri('https://other.com/home')));
    }

    #[Test]
    public function testRejectsAPublicSuffixDomain(): void
    {
        $jar = new CookieJar();
        $jar->storeFromResponse(new Uri('https://abc.com/login'), new Response(200, ['Set-Cookie' => 'sid=abc; Domain=com; Path=/']));

        self::assertNull($jar->headerFor(new Uri('https://victim.com/home')));
        self::assertNull($jar->headerFor(new Uri('https://abc.com/home')));
    }

    #[Test]
    public function testHonoursThePathAttribute(): void
    {
        $jar = new CookieJar();
        $jar->storeFromResponse(new Uri('https://abc.com/admin/login'), new Response(200, ['Set-Cookie' => 'a=1; Path=/admin']));

        self::assertSame('a=1', $jar->headerFor(new Uri('https://abc.com/admin/panel')));
        self::assertNull($jar->headerFor(new Uri('https://abc.com/public')));
    }

    #[Test]
    public function testUsesTheDefaultPathWhenNoneIsSet(): void
    {
        $jar = new CookieJar();
        $jar->storeFromResponse(new Uri('https://abc.com/admin/login'), new Response(200, ['Set-Cookie' => 'a=1']));

        self::assertSame('a=1', $jar->headerFor(new Uri('https://abc.com/admin/panel')));
        self::assertNull($jar->headerFor(new Uri('https://abc.com/')));
    }

    #[Test]
    public function testSkipsSecureCookiesOverPlainHttp(): void
    {
        $jar = new CookieJar();
        $jar->storeFromResponse(new Uri('https://abc.com/login'), new Response(200, ['Set-Cookie' => 's=1; Secure; Path=/']));

        self::assertNull($jar->headerFor(new Uri('http://abc.com/home')));
        self::assertSame('s=1', $jar->headerFor(new Uri('https://abc.com/home')));
    }

    #[Test]
    public function testMaxAgeZeroDeletesAnExistingCookie(): void
    {
        $jar = new CookieJar();
        $jar->storeFromResponse(new Uri('https://abc.com/login'), new Response(200, ['Set-Cookie' => 'sid=abc; Path=/']));
        $jar->storeFromResponse(new Uri('https://abc.com/login'), new Response(200, ['Set-Cookie' => 'sid=; Max-Age=0; Path=/']));

        self::assertNull($jar->headerFor(new Uri('https://abc.com/home')));
    }

    #[Test]
    public function testSkipsAnExpiredCookie(): void
    {
        $jar = new CookieJar();
        $jar->storeFromResponse(new Uri('https://abc.com/login'), new Response(200, ['Set-Cookie' => 'sid=abc; Expires=Thu, 01 Jan 1970 00:00:00 GMT; Path=/']));

        self::assertNull($jar->headerFor(new Uri('https://abc.com/home')));
    }

    #[Test]
    public function testOrdersCookiesByDescendingPathLength(): void
    {
        $jar = new CookieJar();
        $jar->storeFromResponse(new Uri('https://abc.com/admin/login'), new Response(200, [
            'Set-Cookie' => ['a=1; Path=/', 'b=2; Path=/admin'],
        ]));

        self::assertSame('b=2; a=1', $jar->headerFor(new Uri('https://abc.com/admin/panel')));
    }

    #[Test]
    public function testIgnoresMalformedSetCookieHeaders(): void
    {
        $jar = new CookieJar();
        $jar->storeFromResponse(new Uri('https://abc.com/login'), new Response(200, ['Set-Cookie' => 'no-equals-sign']));

        self::assertNull($jar->headerFor(new Uri('https://abc.com/home')));
    }
}
