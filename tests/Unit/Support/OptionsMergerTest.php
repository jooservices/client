<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use JOOservices\Client\Support\OptionsMerger;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('unit')]
class OptionsMergerTest extends TestCase
{
    private OptionsMerger $merger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->merger = new OptionsMerger();
    }

    public function test_merges_simple_arrays_with_request_options_taking_precedence(): void
    {
        $base = ['timeout' => 30, 'verify' => true];
        $request = ['timeout' => 60];

        $result = $this->merger->merge($base, $request);

        $this->assertSame([
            'timeout' => 60,
            'verify' => true,
        ], $result);
    }

    public function test_merges_empty_base_options_with_request_options(): void
    {
        $base = [];
        $request = ['timeout' => 60, 'verify' => false];

        $result = $this->merger->merge($base, $request);

        $this->assertSame([
            'timeout' => 60,
            'verify' => false,
        ], $result);
    }

    public function test_merges_base_options_with_empty_request_options(): void
    {
        $base = ['timeout' => 30, 'verify' => true];
        $request = [];

        $result = $this->merger->merge($base, $request);

        $this->assertSame([
            'timeout' => 30,
            'verify' => true,
        ], $result);
    }

    public function test_merges_both_empty_arrays(): void
    {
        $result = $this->merger->merge([], []);

        $this->assertSame([], $result);
    }

    public function test_deep_merges_headers_from_both_arrays(): void
    {
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

        $this->assertSame([
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'BaseClient/1.0',
                'Accept' => 'application/xml',
                'Authorization' => 'Bearer token123',
            ],
        ], $result);
    }

    public function test_handles_headers_in_base_options_only(): void
    {
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

        $this->assertSame([
            'timeout' => 60,
            'headers' => [
                'User-Agent' => 'BaseClient/1.0',
            ],
        ], $result);
    }

    public function test_handles_headers_in_request_options_only(): void
    {
        $base = [
            'timeout' => 30,
        ];

        $request = [
            'headers' => [
                'Authorization' => 'Bearer token123',
            ],
        ];

        $result = $this->merger->merge($base, $request);

        $this->assertSame([
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer token123',
            ],
        ], $result);
    }

    public function test_handles_non_array_headers_in_base_options(): void
    {
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

        $this->assertSame([
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer token123',
            ],
        ], $result);
    }

    public function test_handles_non_array_headers_in_request_options(): void
    {
        $base = [
            'headers' => [
                'User-Agent' => 'BaseClient/1.0',
            ],
        ];

        $request = [
            'headers' => 'invalid',
        ];

        $result = $this->merger->merge($base, $request);

        $this->assertSame([
            'headers' => 'invalid',
        ], $result);
    }

    public function test_merges_complex_nested_options(): void
    {
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

        $this->assertSame([
            'timeout' => 60,
            'headers' => [
                'User-Agent' => 'BaseClient/1.0',
                'Authorization' => 'Bearer token123',
            ],
            'query' => ['page' => 2, 'limit' => 10],
            'verify' => true,
        ], $result);
    }

    public function test_handles_null_values_in_options(): void
    {
        $base = [
            'timeout' => 30,
            'verify' => true,
        ];

        $request = [
            'timeout' => null,
        ];

        $result = $this->merger->merge($base, $request);

        $this->assertSame([
            'timeout' => null,
            'verify' => true,
        ], $result);
    }

    public function test_preserves_numeric_keys(): void
    {
        $base = [
            0 => 'value1',
            'key' => 'value2',
        ];

        $request = [
            1 => 'value3',
            'key' => 'value4',
        ];

        $result = $this->merger->merge($base, $request);

        $this->assertSame([
            0 => 'value1',
            'key' => 'value4',
            1 => 'value3',
        ], $result);
    }

    public function test_handles_empty_header_arrays(): void
    {
        $base = [
            'timeout' => 30,
            'headers' => [],
        ];

        $request = [
            'headers' => [],
        ];

        $result = $this->merger->merge($base, $request);

        $this->assertSame([
            'timeout' => 30,
            'headers' => [],
        ], $result);
    }

    public function test_merges_case_sensitive_header_keys_correctly(): void
    {
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

        $this->assertSame([
            'Content-Type' => 'application/xml',
            'content-type' => 'should-be-different',
        ], $result['headers']);
    }
}
