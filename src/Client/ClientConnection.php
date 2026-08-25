<?php

declare(strict_types=1);

namespace JOOservices\Client\Client;

final readonly class ClientConnection
{
    /**
     * @param array<string, string|list<string>> $headers
     * @param bool|array<string, mixed> $allowRedirects
     * @param string|array<string, mixed>|null $proxy
     */
    public function __construct(
        public string $baseUri,
        public float $timeout,
        public float $connectTimeout,
        public array $headers,
        public bool $verifySsl,
        public bool|array $allowRedirects,
        public string|array|null $proxy,
    ) {
    }
}
