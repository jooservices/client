<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use JOOservices\Client\Exceptions\JsonDecodingException;
use JOOservices\Client\Response\ResponseWrapper;

test('it returns status code', function () {
    $psr = new Response(201);
    $wrapper = new ResponseWrapper($psr);
    expect($wrapper->status())->toBe(201);
});

test('it decodes json', function () {
    $psr = new Response(200, [], json_encode(['foo' => 'bar']));
    $wrapper = new ResponseWrapper($psr);

    expect($wrapper->json())->toBe(['foo' => 'bar']);
});

test('it returns empty array for empty body', function () {
    $psr = new Response(200, [], '');
    $wrapper = new ResponseWrapper($psr);

    expect($wrapper->json())->toBe([]);
});

test('it throws exception on invalid json', function () {
    $psr = new Response(200, [], '{invalid_json');
    $wrapper = new ResponseWrapper($psr);

    $wrapper->json();
})->throws(JsonDecodingException::class);

test('it returns header value', function () {
    $psr = new Response(200, ['Content-Type' => 'application/json']);
    $wrapper = new ResponseWrapper($psr);

    expect($wrapper->header('Content-Type'))->toBe('application/json');
});

test('it returns null for missing header', function () {
    $psr = new Response(200);
    $wrapper = new ResponseWrapper($psr);

    expect($wrapper->header('X-Missing-Header'))->toBeNull();
});

test('it returns psr response', function () {
    $psr = new Response(200, [], 'body');
    $wrapper = new ResponseWrapper($psr);

    expect($wrapper->toPsrResponse())->toBe($psr);
});

test('it throws exception when toDto class does not exist', function () {
    $psr = new Response(200, [], '{}');
    $wrapper = new ResponseWrapper($psr);

    $wrapper->toDto('NonExistentClass');
})->throws(\InvalidArgumentException::class, 'does not exist');

test('it throws exception when toDto class lacks from method', function () {
    $psr = new Response(200, [], '{}');
    $wrapper = new ResponseWrapper($psr);

    // stdClass exists but has no from() method
    $wrapper->toDto(\stdClass::class);
})->throws(\InvalidArgumentException::class, 'must have a static from() method');

test('it throws exception when json is not array', function () {
    $psr = new Response(200, [], '"just a string"');
    $wrapper = new ResponseWrapper($psr);

    $wrapper->json();
})->throws(JsonDecodingException::class, 'not an array');
