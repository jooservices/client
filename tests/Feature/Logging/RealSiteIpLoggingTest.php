<?php

declare(strict_types=1);

use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Logging\MongoDbLogger;

// Real-network integration test. Run manually with JOOCLIENT_RUN_LIVE_NETWORK_TESTS=1.
test('it logs ip metadata for real public domains', function () {
    $runLive = getenv('JOOCLIENT_RUN_LIVE_NETWORK_TESTS');
    if ($runLive !== '1') {
        $this->markTestSkipped('Set JOOCLIENT_RUN_LIVE_NETWORK_TESTS=1 to run live network tests.');
    }

    $documents = [];
    $logger = new MongoDbLogger(
        writer: function (array $document) use (&$documents): void {
            $documents[] = $document;
        }
    );

    $client = ClientBuilder::create()
        ->withLogger($logger)
        ->withTimeout(15)
        ->withConnectTimeout(10)
        ->build();

    $targets = [
        'https://onejav.com',
        'https://google.com',
        'https://microsoft.com',
    ];

    foreach ($targets as $target) {
        $client->get($target, [
            'allow_redirects' => true,
        ]);
    }

    foreach ($targets as $target) {
        $host = parse_url($target, PHP_URL_HOST);

        $requestDoc = null;
        $responseDoc = null;

        foreach ($documents as $document) {
            $message = (string) ($document['message'] ?? '');
            if ($requestDoc === null && str_contains($message, "Sending request to GET {$target}")) {
                $requestDoc = $document;
            }

            if ($responseDoc === null && str_contains($message, "for GET {$target}")) {
                $responseDoc = $document;
            }
        }

        expect($requestDoc)->not->toBeNull();
        expect($responseDoc)->not->toBeNull();

        expect($requestDoc)->toHaveKey('target_hostname', $host);
        expect($responseDoc)->toHaveKey('target_hostname', $host);
        expect($responseDoc)->toHaveKey('target_ip');
        expect($responseDoc)->toHaveKey('local_ip');
    }
})->group('integration', 'live-network');
