<?php

declare(strict_types=1);

namespace JOOservices\Client\Tests\Unit\Request;

use JOOservices\Client\Exceptions\InvalidConfigurationException;
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
}
