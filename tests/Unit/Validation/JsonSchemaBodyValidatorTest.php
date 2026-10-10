<?php

declare(strict_types=1);

namespace JOOservices\Client\Tests\Unit\Validation;

use JOOservices\Client\Exceptions\DownloadSizeExceededException;
use JOOservices\Client\Validation\JsonSchemaBodyValidator;
use JsonSchema\Validator as JsonSchemaValidator;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

final class JsonSchemaBodyValidatorTest extends TestCase
{
    #[Test]
    public function testRejectsKnownOversizedResponseBeforeSchemaValidation(): void
    {
        if (! class_exists(JsonSchemaValidator::class)) {
            self::markTestSkipped('JSON Schema validation requires justinrainbow/json-schema.');
        }

        $stream = $this->createMock(StreamInterface::class);
        $stream->method('getSize')->willReturn(104_857_601);
        $stream->expects(self::never())->method('read');
        $response = new Response(200, [], $stream);

        $this->expectException(DownloadSizeExceededException::class);
        (new JsonSchemaBodyValidator(['type' => 'object']))->validate($response);
    }
}
