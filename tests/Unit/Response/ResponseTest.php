<?php

declare(strict_types=1);

namespace JOOservices\Client\Tests\Unit\Response;

use JOOservices\Client\Exceptions\DownloadSizeExceededException;
use JOOservices\Client\Exceptions\HttpResponseException;
use JOOservices\Client\Exceptions\JsonDecodingException;
use JOOservices\Client\Response\Response;
use JOOservices\Client\Tests\Fixtures\UserDto;
use Nyholm\Psr7\Response as PsrResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    #[Test]
    public function testJsonIsCachedAfterBodyRead(): void
    {
        $wrap = Response::from(new PsrResponse(200, [], '{"name":"Viet"}'));
        self::assertSame('{"name":"Viet"}', $wrap->body());
        self::assertSame(['name' => 'Viet'], $wrap->json());
        self::assertSame(['name' => 'Viet'], $wrap->json());
        self::assertSame('{"name":"Viet"}', $wrap->body());
    }

    #[Test]
    public function testInvalidJsonThrows(): void
    {
        $this->expectException(JsonDecodingException::class);
        Response::from(new PsrResponse(200, [], '<html>'))->json();
    }

    #[Test]
    public function testJsonScalarThrows(): void
    {
        $this->expectException(JsonDecodingException::class);
        Response::from(new PsrResponse(200, [], 'true'))->json();
    }

    #[Test]
    public function testThrowOn404(): void
    {
        $this->expectException(HttpResponseException::class);
        Response::from(new PsrResponse(404))->throw();
    }

    #[Test]
    public function testThrowOn200ReturnsSelf(): void
    {
        $wrap = Response::from(new PsrResponse(201));
        self::assertSame($wrap, $wrap->throw());
        self::assertSame(201, $wrap->status());
    }

    #[Test]
    public function testToDtoRequiresDtoClass(): void
    {
        $this->expectException(JsonDecodingException::class);
        Response::from(new PsrResponse(200, [], '{"name":"x"}'))->toDto(\stdClass::class);
    }

    #[Test]
    public function testCollectHydratesList(): void
    {
        $items = Response::from(new PsrResponse(200, [], '[{"name":"A"},{"name":"B"}]'))->collect(UserDto::class);
        self::assertCount(2, $items);
        self::assertInstanceOf(UserDto::class, $items[0]);
        self::assertSame('A', $items[0]->name);
    }

    #[Test]
    public function testCollectThrowsOnANonArrayRowInsteadOfSilentlyHydratingAnEmptyDto(): void
    {
        $this->expectException(JsonDecodingException::class);
        Response::from(new PsrResponse(200, [], '[{"name":"A"},"not-an-object"]'))->collect(UserDto::class);
    }

    #[Test]
    public function testBodyRejectsResponsesLargerThanTheSizeLimit(): void
    {
        $oversized = str_repeat('x', 104_857_601);
        $this->expectException(DownloadSizeExceededException::class);
        Response::from(new PsrResponse(200, [], $oversized))->body();
    }

    #[Test]
    public function testStatusHelpersObjectAndBomJsonWork(): void
    {
        $response = Response::from(new \Nyholm\Psr7\Response(201, [], "\xEF\xBB\xBF{\"name\":\"Viet\"}"));
        self::assertTrue($response->successful());
        self::assertFalse($response->failed());
        self::assertFalse($response->clientError());
        self::assertFalse($response->serverError());
        self::assertSame('Viet', get_object_vars($response->object())['name']);

        $error = Response::from(new \Nyholm\Psr7\Response(404));
        self::assertTrue($error->failed());
        self::assertTrue($error->clientError());
        self::assertFalse($error->serverError());
    }
}
