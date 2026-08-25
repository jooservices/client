<?php

declare(strict_types=1);

namespace JOOservices\Client\Middleware;

use JOOservices\Client\Contracts\MiddlewareInterface;
use JOOservices\Client\Contracts\RequestHandlerInterface;
use JOOservices\Client\Dto\RequestOptions;
use JOOservices\Client\Dto\TraceContextConfig;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class TraceContextMiddleware implements MiddlewareInterface
{
    /** RFC-shaped: version(2)-traceid(32)-spanid(16)-flags(2), all lowercase hex, hyphen-separated. */
    private const W3C_TRACEPARENT_PATTERN = '/^([0-9a-f]{2})-([0-9a-f]{32})-([0-9a-f]{16})-[0-9a-f]{2}$/';

    /** Reserved/invalid per the W3C Trace Context spec: version 0xff is reserved, and an all-zero trace-id or span-id is explicitly invalid. */
    private const INVALID_VERSION = 'ff';
    private const INVALID_TRACE_ID = '00000000000000000000000000000000';
    private const INVALID_SPAN_ID = '0000000000000000';

    public function __construct(private readonly TraceContextConfig $config = new TraceContextConfig())
    {
    }

    public function process(RequestInterface $request, RequestOptions $options, RequestHandlerInterface $handler): ResponseInterface
    {
        $existing = $request->getHeaderLine($this->config->header);
        if (! $this->isValidTraceparent($existing)) {
            $trace = bin2hex(random_bytes(16));
            $span = bin2hex(random_bytes(8));
            $request = $request->withHeader($this->config->header, '00-' . $trace . '-' . $span . '-01');
        }

        return $handler->handle($request, $options);
    }

    private function isValidTraceparent(string $value): bool
    {
        if (preg_match(self::W3C_TRACEPARENT_PATTERN, $value, $matches) !== 1) {
            return false;
        }

        return $matches[1] !== self::INVALID_VERSION
            && $matches[2] !== self::INVALID_TRACE_ID
            && $matches[3] !== self::INVALID_SPAN_ID;
    }
}
