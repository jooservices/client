<?php

declare(strict_types=1);

namespace JOOservices\Client\Tests\Unit\Request;

use JOOservices\Client\Exceptions\InvalidConfigurationException;
use JOOservices\Client\Request\MultipartPart;
use JOOservices\Client\Request\RequestBuilder;
use JOOservices\Client\Tests\Fixtures\UserDto;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RequestBuilderTest extends TestCase
{
    #[Test]
    public function testBuildsJsonRequestsAndKeepsTransportOptionsOutOfPsrRequest(): void
    {
        $factory = new Psr17Factory();
        $user = UserDto::from(['name' => 'Viet', 'email' => 'viet@abc.com']);
        $prepared = RequestBuilder::create($factory, $factory, $factory)
            ->post('/users')
            ->withJson($user)
            ->withTimeout(2)
            ->build();

        self::assertSame('POST', $prepared->toPsr()->getMethod());
        self::assertStringContainsString('Viet', (string) $prepared->toPsr()->getBody());
        self::assertSame('application/json', $prepared->toPsr()->getHeaderLine('Content-Type'));
        self::assertSame(2.0, $prepared->options()->timeout);
        self::assertSame('', $prepared->toPsr()->getHeaderLine('X-Timeout'));
    }

    #[Test]
    public function testRejectsHeaderInjection(): void
    {
        $factory = new Psr17Factory();
        $builder = RequestBuilder::create($factory, $factory, $factory)->get('https://api.example.test');

        $this->expectException(InvalidConfigurationException::class);
        $builder->withHeader('X-Test', "safe\r\nInjected: 1");
    }

    #[Test]
    public function testRequiresUri(): void
    {
        $factory = new Psr17Factory();
        $this->expectException(InvalidConfigurationException::class);
        RequestBuilder::create($factory, $factory, $factory)->build();
    }

    #[Test]
    public function testWithQueryEncodesSpaces(): void
    {
        $factory = new Psr17Factory();
        $psr = RequestBuilder::create($factory, $factory, $factory)->get('https://abc.com/x')->withQuery(['q' => 'a b'])->toPsr();
        self::assertSame('q=a%20b', $psr->getUri()->getQuery());
    }

    #[Test]
    public function testWithQueryDecodesAPreExistingPlusAsASpaceNotALiteralPlus(): void
    {
        $factory = new Psr17Factory();
        $psr = RequestBuilder::create($factory, $factory, $factory)->get('https://abc.com/x?q=hello+world')->withQuery(['page' => 2])->toPsr();
        self::assertSame('q=hello%20world&page=2', $psr->getUri()->getQuery());
    }

    #[Test]
    public function testWithQueryPreservesADottedKeyInAPreExistingQueryString(): void
    {
        $factory = new Psr17Factory();
        $psr = RequestBuilder::create($factory, $factory, $factory)->get('https://abc.com/x?filter.name=abc')->withQuery(['page' => 2])->toPsr();
        self::assertSame('filter.name=abc&page=2', $psr->getUri()->getQuery());
    }

    #[Test]
    public function testWithQueryGroupsDuplicateKeysInAPreExistingQueryStringInsteadOfLosingThem(): void
    {
        $factory = new Psr17Factory();
        $psr = RequestBuilder::create($factory, $factory, $factory)->get('https://abc.com/x?tag=a&tag=b')->withQuery(['page' => 2])->toPsr();
        self::assertSame('tag%5B0%5D=a&tag%5B1%5D=b&page=2', $psr->getUri()->getQuery());
    }

    #[Test]
    public function testSupportsAllVerbsHeadersAndTransportOptions(): void
    {
        $factory = new Psr17Factory();
        $builder = RequestBuilder::create($factory, $factory, $factory);
        self::assertSame('PUT', $builder->put('/items')->toPsr()->getMethod());
        self::assertSame('PATCH', $builder->patch('/items')->toPsr()->getMethod());
        self::assertSame('DELETE', $builder->delete('/items')->toPsr()->getMethod());
        self::assertSame('HEAD', $builder->head('/items')->toPsr()->getMethod());

        $prepared = $builder->post('/items')
            ->withHeaders(['Set-Cookie' => ['a=1', 'b=2']])
            ->withProxy('http://proxy.test')
            ->withConnectTimeout(2)
            ->withVerifySsl(false)
            ->withRedirects(false)
            ->build();
        self::assertSame(['a=1', 'b=2'], $prepared->toPsr()->getHeader('Set-Cookie'));
        self::assertSame('http://proxy.test', $prepared->options()->proxy);
        self::assertSame(2.0, $prepared->options()->connectTimeout);
        self::assertFalse($prepared->options()->verifySsl);
        self::assertFalse($prepared->options()->allowRedirects);
    }

    #[Test]
    public function testBuildsAMultipartBodyWithFieldsAndAFile(): void
    {
        $factory = new Psr17Factory();
        $handle = $this->handle("hello\0world");
        $prepared = RequestBuilder::create($factory, $factory, $factory)
            ->post('/media')
            ->withHeader('Content-Type', 'application/json')
            ->withMultipart([
                ['name' => 'title', 'contents' => 'Photo'],
                ['name' => 'file', 'contents' => $handle, 'filename' => 'a.bin', 'contentType' => 'application/octet-stream'],
            ])
            ->build();

        $psr = $prepared->toPsr();
        $type = $psr->getHeaderLine('Content-Type');
        self::assertMatchesRegularExpression('/^multipart\/form-data; boundary=[0-9a-f]{32}$/', $type);
        $boundary = substr($type, strlen('multipart/form-data; boundary='));
        $body = (string) $psr->getBody();
        self::assertSame($psr->getBody()->getSize(), strlen($body));
        self::assertStringContainsString("name=\"title\"\r\n\r\nPhoto\r\n", $body);
        self::assertStringContainsString('filename="a.bin"', $body);
        self::assertStringContainsString("hello\0world", $body);
        self::assertStringEndsWith("--{$boundary}--\r\n", $body);
        fclose($handle);
    }

    #[Test]
    public function testMultipartAcceptsPartObjectsAndRewindsForASecondRead(): void
    {
        $factory = new Psr17Factory();
        $stream = $factory->createStream('file-bytes');
        $prepared = RequestBuilder::create($factory, $factory, $factory)
            ->post('/media')
            ->withMultipart([
                new MultipartPart('note', 'ok'),
                new MultipartPart('file', $stream, 'n.txt', 'text/plain'),
            ])
            ->build();

        $body = $prepared->toPsr()->getBody();
        $first = $body->getContents();
        $body->rewind();
        self::assertSame($first, $body->getContents());
        self::assertStringContainsString('name="note"', $first);
        self::assertStringContainsString('filename="n.txt"', $first);
        self::assertStringContainsString('Content-Type: text/plain', $first);
    }

    #[Test]
    public function testMultipartUsesBlobFilenameWhenAStreamHasNoFilename(): void
    {
        $factory = new Psr17Factory();
        $psr = RequestBuilder::create($factory, $factory, $factory)
            ->post('/media')
            ->withMultipart([['name' => 'file', 'contents' => $factory->createStream('x')]])
            ->toPsr();

        self::assertStringContainsString('filename="blob"', (string) $psr->getBody());
        self::assertStringContainsString('Content-Type: application/octet-stream', (string) $psr->getBody());
    }

    #[Test]
    public function testJsonAfterMultipartReplacesTheContentType(): void
    {
        $factory = new Psr17Factory();
        $psr = RequestBuilder::create($factory, $factory, $factory)
            ->post('/media')
            ->withMultipart([['name' => 'title', 'contents' => 'Photo']])
            ->withJson(['ok' => true])
            ->toPsr();

        self::assertSame('application/json', $psr->getHeaderLine('Content-Type'));
        self::assertSame('{"ok":true}', (string) $psr->getBody());
    }

    #[Test]
    public function testRejectsAnEmptyMultipartPartList(): void
    {
        $factory = new Psr17Factory();
        $this->expectException(InvalidConfigurationException::class);
        RequestBuilder::create($factory, $factory, $factory)->post('/media')->withMultipart([]);
    }

    #[Test]
    public function testRejectsHeaderInjectionInMultipartNames(): void
    {
        $factory = new Psr17Factory();
        $this->expectException(InvalidConfigurationException::class);
        RequestBuilder::create($factory, $factory, $factory)
            ->post('/media')
            ->withMultipart([['name' => "file\r\nX-Injected: 1", 'contents' => 'x']]);
    }

    #[Test]
    public function testRejectsCrLfInMultipartTextFields(): void
    {
        $factory = new Psr17Factory();
        $this->expectException(InvalidConfigurationException::class);
        RequestBuilder::create($factory, $factory, $factory)
            ->post('/media')
            ->withMultipart([['name' => 'title', 'contents' => "safe\r\n--forged"]]);
    }

    /** @return resource */
    private function handle(string $content): mixed
    {
        $handle = fopen('php://temp', 'r+b');
        self::assertIsResource($handle);
        fwrite($handle, $content);
        rewind($handle);

        return $handle;
    }
}
