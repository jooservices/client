<?php

declare(strict_types=1);

namespace JOOservices\Client\Tests\Unit\Support;

use JOOservices\Client\Exceptions\DownloadSizeExceededException;
use JOOservices\Client\Logging\LogSanitizer;
use JOOservices\Client\Signing\HmacSha256Signer;
use JOOservices\Client\Support\DownloadSizeGuard;
use JOOservices\Client\Support\HostPartitionKeyResolver;
use JOOservices\Client\Support\InMemoryMetricsRecorder;
use JOOservices\Client\Support\RetryAfterHeader;
use JOOservices\Client\Support\UsleepSleeper;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SupportUtilitiesTest extends TestCase
{
    #[Test]
    public function testSanitizesSecretsAndSignsWithoutConsumingStream(): void
    {
        $sanitized = (new LogSanitizer())->sanitize(['Authorization' => 'Bearer secret', 'uri' => 'https://x.test/?token=secret']);
        self::assertSame('[redacted]', $sanitized['Authorization']);
        self::assertSame('https://x.test/?token=[redacted]', $sanitized['uri']);

        $oauthSanitized = (new LogSanitizer())->sanitize(['uri' => 'https://x.test/?access_token=abc&client_secret=def&refresh_token=ghi&sig=jkl&keep=me']);
        self::assertSame('https://x.test/?access_token=[redacted]&client_secret=[redacted]&refresh_token=[redacted]&sig=[redacted]&keep=me', $oauthSanitized['uri']);

        $credentialSanitized = (new LogSanitizer())->sanitize(['uri' => 'https://user:secretpass@api.example.com/path?token=abc']);
        self::assertSame('https://[redacted]@api.example.com/path?token=[redacted]', $credentialSanitized['uri']);
        $noCredentials = (new LogSanitizer())->sanitize(['uri' => 'https://api.example.com/path']);
        self::assertSame('https://api.example.com/path', $noCredentials['uri']);
        $nested = (new LogSanitizer())->sanitize(['nested' => ['password' => 'secret']])['nested'];
        self::assertIsArray($nested);
        self::assertSame('[redacted]', $nested['password']);

        // A signed request's headers, as logged by LoggingMiddleware: getHeaders() nests each header
        // name under 'headers', so this exercises isSecret() on 'X-Signature'/'X-Jwt'/'X-Session-Id'
        // specifically, not just the top-level key case already covered above.
        $headerContext = (new LogSanitizer())->sanitize(['headers' => [
            'X-Signature' => ['deadbeef'],
            'X-Jwt' => ['eyJ'],
            'X-Session-Id' => ['abc123'],
        ]]);
        $headers = $headerContext['headers'];
        self::assertIsArray($headers);
        self::assertSame('[redacted]', $headers['X-Signature']);
        self::assertSame('[redacted]', $headers['X-Jwt']);
        self::assertSame('[redacted]', $headers['X-Session-Id']);

        $factory = new Psr17Factory();
        $request = $factory->createRequest('POST', 'https://api.test/a')->withBody($factory->createStream('body'));
        $request->getBody()->seek(2);
        $signed = (new HmacSha256Signer('key'))->sign($request);
        self::assertNotSame('', $signed->getHeaderLine('X-Signature'));
        self::assertSame(2, $request->getBody()->tell());
    }

    #[Test]
    public function testGuardsDownloadsAndProvidesSmallUtilities(): void
    {
        $guard = new DownloadSizeGuard();
        self::assertSame(200, $guard->assertWithin(new Response(200, ['Content-Length' => '5']), 5)->getStatusCode());
        $this->expectException(DownloadSizeExceededException::class);
        $guard->assertWithin(new Response(200, ['Content-Length' => '6']), 5);
    }

    #[Test]
    public function testMetricsRetryAndHostUtilities(): void
    {
        $metrics = new InMemoryMetricsRecorder();
        $metrics->increment('count');
        $metrics->observe('duration', 1.2);
        self::assertCount(2, $metrics->values());
        self::assertSame(2000, (new RetryAfterHeader())->milliseconds('2', 1));
        self::assertSame(1, (new RetryAfterHeader())->milliseconds('invalid', 1));

        $header = new RetryAfterHeader(maxMilliseconds: 5000);
        self::assertSame(5000, $header->milliseconds('99999999999999999999', 1));
        self::assertSame(5000, $header->milliseconds('86400', 1));
        self::assertSame(0, $header->milliseconds('0', 1));

        (new UsleepSleeper())->sleep(0);
        (new UsleepSleeper())->sleep(1);
        self::assertSame('https://api.test:0', (new HostPartitionKeyResolver())->resolve((new Psr17Factory())->createRequest('GET', 'https://api.test/path')));
    }
}
