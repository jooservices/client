<?php

declare(strict_types=1);

namespace JOOservices\Client\Tests\Unit\Support;

use JOOservices\Client\Support\BaseUri;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BaseUriTest extends TestCase
{
    #[Test]
    public function testAddsTrailingSlashSoPrefixDoesNotDropLastSegment(): void
    {
        $base = new BaseUri();
        self::assertSame('https://abc.com/v1/', $base->normalize('https://abc.com/v1'));
        self::assertSame('https://abc.com/v1/', $base->normalize('https://abc.com/v1/'));
        self::assertSame('https://abc.com/', $base->normalize('https://abc.com'));
        self::assertSame('https://site.example.test/wp-json/', $base->normalize('https://site.example.test/wp-json'));
    }
}
