<?php

declare(strict_types=1);

use JOOservices\Client\Logging\MongoDbLogger;

it('captures request payload with trimming', function () {
    $documents = [];

    $logger = new MongoDbLogger(
        maxRequestBodyBytes: 5,
        writer: function (array $document) use (&$documents): void {
            $documents[] = $document;
        }
    );

    $logger->debug('Request Body', ['body' => 'abcdef']);

    expect($documents)->toHaveCount(1);
    expect($documents[0])->toHaveKey('request_payload', 'abcde');
    expect($documents[0])->toHaveKey('payload_truncated', true);
});

it('captures response payload with configurable trimming', function () {
    $documents = [];

    $logger = new MongoDbLogger(
        maxResponseBodyBytes: 4,
        writer: function (array $document) use (&$documents): void {
            $documents[] = $document;
        }
    );

    $logger->debug('Response Body', ['body' => '123456']);

    expect($documents)->toHaveCount(1);
    expect($documents[0])->toHaveKey('response_payload', '1234');
    expect($documents[0])->toHaveKey('payload_truncated', true);
});

it('maps common request metadata fields', function () {
    $documents = [];

    $logger = new MongoDbLogger(
        writer: function (array $document) use (&$documents): void {
            $documents[] = $document;
        }
    );

    $logger->info('Sending request to GET https://example.com', [
        'method' => 'GET',
        'uri' => 'https://example.com',
        'correlation_id' => 'abc-123',
    ]);

    expect($documents)->toHaveCount(1);
    expect($documents[0])->toHaveKey('method', 'GET');
    expect($documents[0])->toHaveKey('uri', 'https://example.com');
    expect($documents[0])->toHaveKey('correlation_id', 'abc-123');
});

it('redacts sensitive headers in context', function () {
    $documents = [];

    $logger = new MongoDbLogger(
        writer: function (array $document) use (&$documents): void {
            $documents[] = $document;
        }
    );

    $logger->debug('Request Body', [
        'headers' => [
            'Authorization' => 'Bearer secret',
            'X-Test' => 'safe',
        ],
        'body' => 'ok',
    ]);

    expect($documents)->toHaveCount(1);
    expect($documents[0]['context']['headers']['Authorization'])->toBe('[REDACTED]');
    expect($documents[0]['context']['headers']['X-Test'])->toBe('safe');
});

it('requires non negative trim values', function () {
    expect(fn () => new MongoDbLogger(maxRequestBodyBytes: -1, writer: static function (array $d): void {
    }))
        ->toThrow(RuntimeException::class);

    expect(fn () => new MongoDbLogger(maxResponseBodyBytes: -1, writer: static function (array $d): void {
    }))
        ->toThrow(RuntimeException::class);
});

it('does not throw when default persistence backend is unavailable', function () {
    $logger = new MongoDbLogger();

    expect(fn () => $logger->info('test'))
        ->not->toThrow(RuntimeException::class);
});
