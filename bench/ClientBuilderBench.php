<?php

declare(strict_types=1);

namespace JOOservices\Client\Bench;

use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Testing\FakeTransport;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;

final class ClientBuilderBench
{
    #[Revs(100)]
    #[Iterations(3)]
    public function benchBuild(): void
    {
        ClientBuilder::create()
            ->withBaseUri('https://api.example.test/v1')
            ->withTransport(new FakeTransport())
            ->build();
    }
}
