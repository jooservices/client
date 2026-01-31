<?php

declare(strict_types=1);

use JOOservices\Client\Support\OptionsMerger;

describe('OptionsMerger', function () {
    beforeEach(function () {
        $this->merger = new OptionsMerger();
    });

    it('merges simple arrays with request options taking precedence', function () {
        $base = ['timeout' => 30, 'verify' => true];
        $request = ['timeout' => 60];

        $result = $this->merger->merge($base, $request);

        expect($result)->toBe([
            'timeout' => 60,
            'verify' => true,
        ]);
    });

    it('merges empty base options with request options', function () {
        $base = [];
        $request = ['timeout' => 60, 'verify' => false];

        $result = $this->merger->merge($base, $request);

        expect($result)->toBe([
            'timeout' => 60,
            'verify' => false,
        ]);
    });

    it('merges base options with empty request options', function () {
        $base = ['timeout' => 30, 'verify' => true];
        $request = [];

        $result = $this->merger->merge($base, $request);

        expect($result)->toBe([
            'timeout' => 30,
            'verify' => true,
        ]);
    });

    it('merges both empty arrays', function () {
        $result = $this->merger->merge([], []);

        expect($result)->toBe([]);
    });

    it('deep merges headers from both arrays', function () {
        $base = [
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'BaseClient/1.0',
                'Accept' => 'application/json',
            ],
        ];

        $request = [
            'headers' => [
                'Authorization' => 'Bearer token123',
                'Accept' => 'application/xml',
            ],
        ];

        $result = $this->merger->merge($base, $request);

        expect($result)->toBe([
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'BaseClient/1.0',
                'Accept' => 'application/xml', // Request overrides base
                'Authorization' => 'Bearer token123',
            ],
        ]);
    });

    it('handles headers in base options only', function () {
        $base = [
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'BaseClient/1.0',
            ],
        ];

        $request = [
            'timeout' => 60,
        ];

        $result = $this->merger->merge($base, $request);

        expect($result)->toBe([
            'timeout' => 60,
            'headers' => [
                'User-Agent' => 'BaseClient/1.0',
            ],
        ]);
    });

    it('handles headers in request options only', function () {
        $base = [
            'timeout' => 30,
        ];

        $request = [
            'headers' => [
                'Authorization' => 'Bearer token123',
            ],
        ];

        $result = $this->merger->merge($base, $request);

        expect($result)->toBe([
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer token123',
            ],
        ]);
    });

    it('handles non-array headers in base options', function () {
        $base = [
            'timeout' => 30,
            'headers' => 'invalid',
        ];

        $request = [
            'headers' => [
                'Authorization' => 'Bearer token123',
            ],
        ];

        $result = $this->merger->merge($base, $request);

        expect($result)->toBe([
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer token123',
            ],
        ]);
    });

    it('handles non-array headers in request options', function () {
        $base = [
            'headers' => [
                'User-Agent' => 'BaseClient/1.0',
            ],
        ];

        $request = [
            'headers' => 'invalid',
        ];

        $result = $this->merger->merge($base, $request);

        expect($result)->toBe([
            'headers' => 'invalid',
        ]);
    });

    it('merges complex nested options', function () {
        $base = [
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'BaseClient/1.0',
            ],
            'query' => ['page' => 1],
            'verify' => true,
        ];

        $request = [
            'timeout' => 60,
            'headers' => [
                'Authorization' => 'Bearer token123',
            ],
            'query' => ['page' => 2, 'limit' => 10],
        ];

        $result = $this->merger->merge($base, $request);

        expect($result)->toBe([
            'timeout' => 60,
            'headers' => [
                'User-Agent' => 'BaseClient/1.0',
                'Authorization' => 'Bearer token123',
            ],
            'query' => ['page' => 2, 'limit' => 10], // Request replaces base (not deep merged)
            'verify' => true,
        ]);
    });

    it('handles null values in options', function () {
        $base = [
            'timeout' => 30,
            'verify' => true,
        ];

        $request = [
            'timeout' => null,
        ];

        $result = $this->merger->merge($base, $request);

        expect($result)->toBe([
            'timeout' => null,
            'verify' => true,
        ]);
    });

    it('preserves numeric keys', function () {
        $base = [
            0 => 'value1',
            'key' => 'value2',
        ];

        $request = [
            1 => 'value3',
            'key' => 'value4',
        ];

        $result = $this->merger->merge($base, $request);

        expect($result)->toBe([
            0 => 'value1',
            'key' => 'value4',
            1 => 'value3',
        ]);
    });

    it('handles empty header arrays', function () {
        $base = [
            'timeout' => 30,
            'headers' => [],
        ];

        $request = [
            'headers' => [],
        ];

        $result = $this->merger->merge($base, $request);

        expect($result)->toBe([
            'timeout' => 30,
            'headers' => [],
        ]);
    });

    it('merges case-sensitive header keys correctly', function () {
        $base = [
            'headers' => [
                'Content-Type' => 'application/json',
                'content-type' => 'should-be-different',
            ],
        ];

        $request = [
            'headers' => [
                'Content-Type' => 'application/xml',
            ],
        ];

        $result = $this->merger->merge($base, $request);

        expect($result['headers'])->toBe([
            'Content-Type' => 'application/xml',
            'content-type' => 'should-be-different',
        ]);
    });
});
