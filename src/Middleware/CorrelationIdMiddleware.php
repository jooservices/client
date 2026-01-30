<?php

declare(strict_types=1);

namespace JOOservices\Client\Middleware;

use Closure;
use JOOservices\Client\Contracts\MiddlewareInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class CorrelationIdMiddleware implements MiddlewareInterface
{
    public const HEADER_NAME = 'X-Correlation-ID';

    public function __invoke(RequestInterface $request, array $options, Closure $next): ResponseInterface
    {
        $correlationHeader = $options['correlation_header'] ?? self::HEADER_NAME;
        if (!is_string($correlationHeader)) {
            $correlationHeader = self::HEADER_NAME;
        }
        $headerName = $correlationHeader;

        if (!$request->hasHeader($headerName)) {
            // Generate UUID v4 (simple version to avoid Ramsey dependency for now)
            // Or use uniqid() as fallback if random_bytes not avail?
            // PHP 8.2 has random_bytes which is standard.
            $uuid = $this->generateUuid();
            $request = $request->withHeader($headerName, $uuid);
        } else {
            $uuid = $request->getHeaderLine($headerName);
        }

        $response = $next($request, $options);

        // Propagate back to response if not present
        if (!$response->hasHeader($headerName)) {
            $response = $response->withHeader($headerName, $uuid);
        }

        return $response;
    }

    private function generateUuid(): string
    {
        // Simple v4 UUID generation
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
