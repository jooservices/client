<?php

declare(strict_types=1);

namespace JOOservices\Client\Tests\Unit\Support;

use JOOservices\Client\Support\UriResolver;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UriResolverTest extends TestCase
{
    #[Test]
    public function testRfc3986MergeAndAbsolutePath(): void
    {
        $factory = new Psr17Factory();
        $base = $factory->createUri('https://abc.com/v1/');
        $resolver = new UriResolver();
        $append = $resolver->resolve($base, $factory->createUri('users'));
        $replace = $resolver->resolve($base, $factory->createUri('/users'));
        $absolute = $resolver->resolve($base, $factory->createUri('https://other.test/x'));

        self::assertSame('https://abc.com/v1/users', (string) $append);
        self::assertSame('https://abc.com/users', (string) $replace);
        self::assertSame('https://other.test/x', (string) $absolute);
    }

    #[Test]
    public function testNoDoubleSlashWhenBaseEndsWithSlash(): void
    {
        $factory = new Psr17Factory();
        $resolved = new UriResolver()->resolve(
            $factory->createUri('https://abc.com/'),
            $factory->createUri('/users'),
        );
        self::assertSame('https://abc.com/users', (string) $resolved);
    }

    #[Test]
    public function testResolvesQueriesFragmentsAndDotSegments(): void
    {
        $factory = new Psr17Factory();
        $resolver = new UriResolver();
        self::assertSame('https://api.test/c?x=1', (string) $resolver->resolve($factory->createUri('https://api.test/a/b?old=1'), $factory->createUri('../c?x=1')));
        self::assertSame('https://other.test/x', (string) $resolver->resolve($factory->createUri('https://api.test/a'), $factory->createUri('https://other.test/x')));
    }
}
