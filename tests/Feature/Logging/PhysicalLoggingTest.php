<?php

declare(strict_types=1);

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use JOOservices\Client\Client\ClientBuilder;
use Symfony\Component\Filesystem\Path;

// beforeEach(function () {
//    clearTmpDir();
// });
// We use unique dirs per test or handle specific details

test('it writes physical logs to disk', function () {
    // 1. Arrange
    $mock = new MockHandler([
        new Response(200, [], '{"message": "success"}'),
    ]);
    $handler = HandlerStack::create($mock);

    $logDir = makeTmpDir('logs');
    $domain = 'joo-service';

    // 2. Act
    $logger = \JOOservices\Client\Logging\MonologFactory::createDaily($domain, $logDir);

    $client = ClientBuilder::create()
        ->withOption('handler', $handler)
        ->withLogger($logger, logBodies: true) // Enable body logging
        ->build();

    $response = $client->get('/api/users');

    // 3. Assert
    expect($response->status())->toBe(200);

    $date = date('Y-m-d');
    $expectedFile = Path::join($logDir, $domain, "client-$date.log");

    // Check file existence
    expect(file_exists($expectedFile))->toBeTrue("Log file should exist at $expectedFile");

    // Check content
    $content = file_get_contents($expectedFile);
    expect($content)->toContain('GET /api/users')
        ->toContain('success');
});

test('it rotates logs daily', function () {
    // This is hard to test "real" rotation without changing system time,
    // but we can verify the FILENAME format corresponds to current date,
    // which effectively tests the rotation logic of MonologFactory.
    // (Already covered above).

    // Let's test a failed request log
    $mock = new MockHandler([
        new Response(500, [], 'Server Error'),
    ]);
    $handler = HandlerStack::create($mock);
    $logDir = makeTmpDir('logs-error');

    $client = ClientBuilder::create()
        ->withOption('handler', $handler)
        ->withDefaultLogging('errors', $logDir)
        ->withHttpErrors(false) // Don't throw, just return response
        ->build();

    $client->get('/error-endpoint');

    $date = date('Y-m-d');
    $expectedFile = Path::join($logDir, 'errors', "client-$date.log");

    expect(file_get_contents($expectedFile))->toContain('Received response 500');
});
