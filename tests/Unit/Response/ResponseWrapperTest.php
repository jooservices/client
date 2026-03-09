<?php

declare(strict_types=1);

namespace Tests\Unit\Response;

use GuzzleHttp\Psr7\Response;
use JOOservices\Client\Exceptions\JsonDecodingException;
use JOOservices\Client\Response\ResponseWrapper;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('unit')]
class ResponseWrapperTest extends TestCase
{
    public function test_it_returns_status_code(): void
    {
        $psr = new Response(201);
        $wrapper = new ResponseWrapper($psr);
        $this->assertSame(201, $wrapper->status());
    }

    public function test_it_decodes_json(): void
    {
        $psr = new Response(200, [], json_encode(['foo' => 'bar']));
        $wrapper = new ResponseWrapper($psr);

        $this->assertSame(['foo' => 'bar'], $wrapper->json());
    }

    public function test_it_returns_empty_array_for_empty_body(): void
    {
        $psr = new Response(200, [], '');
        $wrapper = new ResponseWrapper($psr);

        $this->assertSame([], $wrapper->json());
    }

    public function test_it_throws_exception_on_invalid_json(): void
    {
        $psr = new Response(200, [], '{invalid_json');
        $wrapper = new ResponseWrapper($psr);

        $this->expectException(JsonDecodingException::class);
        $wrapper->json();
    }

    public function test_it_returns_header_value(): void
    {
        $psr = new Response(200, ['Content-Type' => 'application/json']);
        $wrapper = new ResponseWrapper($psr);

        $this->assertSame('application/json', $wrapper->header('Content-Type'));
    }

    public function test_it_returns_null_for_missing_header(): void
    {
        $psr = new Response(200);
        $wrapper = new ResponseWrapper($psr);

        $this->assertNull($wrapper->header('X-Missing-Header'));
    }

    public function test_it_returns_psr_response(): void
    {
        $psr = new Response(200, [], 'body');
        $wrapper = new ResponseWrapper($psr);

        $this->assertSame($psr, $wrapper->toPsrResponse());
    }

    public function test_it_throws_exception_when_toDto_class_does_not_exist(): void
    {
        $psr = new Response(200, [], '{}');
        $wrapper = new ResponseWrapper($psr);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist');
        $wrapper->toDto('NonExistentClass');
    }

    public function test_it_throws_exception_when_toDto_class_lacks_from_method(): void
    {
        $psr = new Response(200, [], '{}');
        $wrapper = new ResponseWrapper($psr);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must have a static from() method');
        $wrapper->toDto(\stdClass::class);
    }

    public function test_it_throws_exception_when_json_is_not_array(): void
    {
        $psr = new Response(200, [], '"just a string"');
        $wrapper = new ResponseWrapper($psr);

        $this->expectException(JsonDecodingException::class);
        $this->expectExceptionMessage('not an array');
        $wrapper->json();
    }

    public function test_toDto_returns_dto_from_json(): void
    {
        $psr = new Response(200, [], json_encode(['id' => 1, 'name' => 'Test']));
        $wrapper = new ResponseWrapper($psr);

        $dto = $wrapper->toDto(ResponseWrapperTestDto::class);

        $this->assertInstanceOf(ResponseWrapperTestDto::class, $dto);
        $this->assertSame(1, $dto->id);
        $this->assertSame('Test', $dto->name);
    }
}
