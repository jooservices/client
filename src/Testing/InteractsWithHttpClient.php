<?php

declare(strict_types=1);

namespace JOOservices\Client\Testing;

use JOOservices\Client\Client\ClientBuilder;

trait InteractsWithHttpClient
{
    private HttpFakeRegistry $httpFakes;

    protected function setUpHttpFakes(): void
    {
        $this->httpFakes = new HttpFakeRegistry();
    }

    protected function httpFakes(): HttpFakeRegistry
    {
        return $this->httpFakes;
    }

    /**
     * Call from your test framework's teardown/after hook. ClientBuilder::fake()/push()/respond() register
     * process-global static state; a test that fails before reaching its own ClientBuilder::clearFake() call
     * otherwise leaks fake-transport state into every later test in the same process.
     *
     * Clearing that process-global registry has no instance-based equivalent.
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    protected function tearDownHttpFakes(): void
    {
        ClientBuilder::clearFake();
    }
}
