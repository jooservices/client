<?php

declare(strict_types=1);

namespace Tests\Feature\Logging;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use JOOservices\Client\Client\ClientBuilder;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Filesystem\Path;
use Tests\TestCase;

#[Group('feature')]
class PhysicalLoggingTest extends TestCase
{
    public function test_it_writes_physical_logs_to_disk(): void
    {
        $mock = new MockHandler([
            new Response(200, [], '{"message": "success"}'),
        ]);
        $handler = HandlerStack::create($mock);

        $logDir = $this->makeTmpDir('logs');
        $domain = 'joo-service';

        $logger = \JOOservices\Client\Logging\MonologFactory::createDaily($domain, $logDir);

        $client = ClientBuilder::create()
            ->withOption('handler', $handler)
            ->withLogger($logger, logBodies: true)
            ->build();

        $response = $client->get('/api/users');

        $this->assertSame(200, $response->status());

        $date = date('Y-m-d');
        $expectedFile = Path::join($logDir, $domain, "client-$date.log");

        $this->assertTrue(file_exists($expectedFile), "Log file should exist at $expectedFile");

        $content = file_get_contents($expectedFile);
        $this->assertStringContainsString('GET /api/users', $content);
        $this->assertStringContainsString('success', $content);
    }

    public function test_it_rotates_logs_daily(): void
    {
        $mock = new MockHandler([
            new Response(500, [], 'Server Error'),
        ]);
        $handler = HandlerStack::create($mock);
        $logDir = $this->makeTmpDir('logs-error');

        $client = ClientBuilder::create()
            ->withOption('handler', $handler)
            ->withDefaultLogging('errors', $logDir)
            ->withHttpErrors(false)
            ->build();

        $client->get('/error-endpoint');

        $date = date('Y-m-d');
        $expectedFile = Path::join($logDir, 'errors', "client-$date.log");

        $this->assertStringContainsString('Received response 500', file_get_contents($expectedFile));
    }
}
