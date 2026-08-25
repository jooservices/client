<?php

declare(strict_types=1);

namespace JOOservices\Client\Middleware;

use JOOservices\Client\Contracts\MiddlewareInterface;
use JOOservices\Client\Contracts\RequestHandlerInterface;
use JOOservices\Client\Dto\RequestOptions;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class CorrelationIdMiddleware implements MiddlewareInterface
{
    /** A caller-supplied id longer than this is replaced instead of forwarded uncapped downstream. */
    private const MAX_INCOMING_LENGTH = 256;

    public function __construct(private readonly string $header = 'X-Correlation-ID')
    {
    }

    public function process(RequestInterface $request, RequestOptions $options, RequestHandlerInterface $handler): ResponseInterface
    {
        $existing = $request->getHeaderLine($this->header);
        if ($existing === '' || strlen($existing) > self::MAX_INCOMING_LENGTH) {
            $request = $request->withHeader($this->header, bin2hex(random_bytes(16)));
        }

        return $handler->handle($request, $options);
    }
}
