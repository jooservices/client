<?php

declare(strict_types=1);

namespace JOOservices\Client\Tests\Unit\Cache;

use DateInterval;
use JOOservices\Client\Cache\ArrayCache;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ArrayCacheTest extends TestCase
{
    #[Test]
    public function testExpiresEntriesSetWithAPastTtl(): void
    {
        $cache = new ArrayCache();
        $cache->set('a', 'value', -1);
        self::assertNull($cache->get('a'));

        $cache->set('b', 'value', new DateInterval('PT0S'));
        usleep(1000);
        self::assertNull($cache->get('b'));
    }

    #[Test]
    public function testGetsAndDeletesStoredValues(): void
    {
        $cache = new ArrayCache();
        $cache->set('a', 'value');
        self::assertSame('value', $cache->get('a'));
        $cache->delete('a');
        self::assertNull($cache->get('a'));
    }

    #[Test]
    public function testEvictsTheOldestEntryOnceTheCapIsExceeded(): void
    {
        $cache = new ArrayCache(maxEntries: 2);
        $cache->set('a', 'first');
        $cache->set('b', 'second');
        $cache->set('c', 'third');

        self::assertNull($cache->get('a'));
        self::assertSame('second', $cache->get('b'));
        self::assertSame('third', $cache->get('c'));
    }

    #[Test]
    public function testUpdatingAnExistingKeyDoesNotCountAsGrowthTowardTheCap(): void
    {
        $cache = new ArrayCache(maxEntries: 2);
        $cache->set('a', 'first');
        $cache->set('b', 'second');
        $cache->set('a', 'updated');

        self::assertSame('updated', $cache->get('a'));
        self::assertSame('second', $cache->get('b'));
    }
}
