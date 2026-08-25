<?php

declare(strict_types=1);

namespace JOOservices\Client\Tests\Unit\Resilience;

use JOOservices\Client\Resilience\Storage\InMemoryStateStore;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InMemoryStateStoreTest extends TestCase
{
    #[Test]
    public function testMutateAppliesTheCallbackToTheCurrentStateAndPersistsTheResult(): void
    {
        $store = new InMemoryStateStore();

        $increment = static function (?array $state): array {
            $count = is_int($state['count'] ?? null) ? $state['count'] : 0;

            return ['count' => $count + 1];
        };

        $first = $store->mutate('counter', $increment);
        self::assertSame(['count' => 1], $first);

        $second = $store->mutate('counter', $increment);
        self::assertSame(['count' => 2], $second);
        self::assertSame(['count' => 2], $store->get('counter'));
    }

    #[Test]
    public function testMutatePassesNullForAnAbsentKey(): void
    {
        $store = new InMemoryStateStore();
        $seen = 'not-yet-set';

        $store->mutate('missing', function (?array $state) use (&$seen): array {
            $seen = $state;

            return [];
        });

        self::assertNull($seen);
    }
}
