<?php

declare(strict_types=1);

namespace JOOservices\Client\Signing;

use JOOservices\Client\Contracts\RequestSignerInterface;
use JOOservices\Client\Support\StreamContents;
use Psr\Http\Message\RequestInterface;

/**
 * Signs method + URI + body only — headers are NOT covered. A header added or changed after signing
 * (by another middleware, a proxy, or an onRequest interceptor) does not invalidate the signature.
 * Register this via withRequestSigning() early in the middleware order (before anything that mutates
 * headers you need covered), or extend the payload yourself if header integrity matters for your use case.
 */
final class HmacSha256Signer implements RequestSignerInterface
{
    public function __construct(private readonly string $key, private readonly string $header = 'X-Signature', private readonly StreamContents $streams = new StreamContents())
    {
    }

    public function sign(RequestInterface $request): RequestInterface
    {
        $payload = $request->getMethod() . "\n" . (string) $request->getUri() . "\n" . $this->streams->read($request->getBody());

        return $request->withHeader($this->header, hash_hmac('sha256', $payload, $this->key));
    }
}
