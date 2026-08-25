<?php

declare(strict_types=1);

namespace JOOservices\Client\Tests\Unit\Support;

use JOOservices\Client\Exceptions\InvalidConfigurationException;
use JOOservices\Client\Exceptions\RequestException;
use JOOservices\Client\Support\HeaderValidator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

final class HeaderValidatorTest extends TestCase
{
    #[Test]
    public function testRejectsHeaderNameWithColon(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        new HeaderValidator()->assertName('X:Injected');
    }

    #[Test]
    public function testRejectsCrlfInValue(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        new HeaderValidator()->assertValue("ok\r\nX-Injected: 1");
    }

    #[Test]
    public function testRejectsANulByteInValue(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        new HeaderValidator()->assertValue("ok\0evil");
    }

    #[Test]
    public function testAllowsATabInValue(): void
    {
        // HTAB is explicitly permitted by RFC 7230's field-value grammar (used for OWS/BWS folding) —
        // the control-character rejection must not sweep this up along with the genuinely invalid ones.
        new HeaderValidator()->assertValue("ok\tstill-ok");
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function testRequestWithCrlfBecomesRequestException(): void
    {
        $request = self::createStub(RequestInterface::class);
        $request->method('getHeaders')->willReturn(['X-Test' => ["a\nb"]]);
        $this->expectException(RequestException::class);
        new HeaderValidator()->assertRequest($request);
    }
}
