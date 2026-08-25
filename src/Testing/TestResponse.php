<?php

declare(strict_types=1);

namespace JOOservices\Client\Testing;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

final class TestResponse
{
    /** @param array<string, string|list<string>> $headers */
    public static function make(int $status = 200, array $headers = [], string $body = ''): ResponseInterface
    {
        return new Response($status, $headers, $body);
    }

    /** @param array<array-key, mixed> $data */
    public static function json(array $data, int $status = 200): ResponseInterface
    {
        return self::make($status, ['Content-Type' => 'application/json'], json_encode($data, JSON_THROW_ON_ERROR));
    }
}
