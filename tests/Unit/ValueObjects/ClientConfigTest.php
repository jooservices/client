<?php

declare(strict_types=1);

use JOOservices\Client\Exceptions\InvalidConfigurationException;
use JOOservices\Client\ValueObjects\ClientConfig;

test('it sets defaults correctly', function () {
    $config = new ClientConfig();
    expect($config->timeout)->toBe(30)
        ->and($config->connectTimeout)->toBe(10)
        ->and($config->verifySsl)->toBeTrue();
});

test('it converts to guzzle options', function () {
    $config = new ClientConfig(
        baseUri: 'https://example.com',
        timeout: 5,
        options: ['debug' => true]
    );

    $guzzle = $config->toGuzzleOptions();

    expect($guzzle)->toBeArray()
        ->and($guzzle['base_uri'])->toBe('https://example.com')
        ->and($guzzle['timeout'])->toBe(5)
        ->and($guzzle['debug'])->toBeTrue()
        ->and($guzzle['http_errors'])->toBeFalse();
});

test('it throws on negative timeout', function () {
    new ClientConfig(timeout: -1);
})->throws(InvalidConfigurationException::class);

test('create from array with options resolver', function () {
    $config = ClientConfig::fromArray([
        'timeout' => 50,
        'baseUri' => 'https://api.test.com',
    ]);

    expect($config->timeout)->toBe(50)
        ->and($config->baseUri)->toBe('https://api.test.com');
});
