<?php

declare(strict_types=1);

namespace JOOservices\Client\Dto;

use JOOservices\Client\Exceptions\InvalidConfigurationException;
use JOOservices\Dto\Core\Dto;

final class ClientConfig extends Dto
{
    /**
     * @param array<string, string|list<string>> $headers
     * @param bool|array<string, mixed> $allowRedirects
     * @param string|array<string, mixed>|null $proxy
     */
    public function __construct(
        public readonly string $baseUri = '',
        public readonly float $timeout = 30.0,
        public readonly float $connectTimeout = 10.0,
        public readonly array $headers = [],
        public readonly bool $verifySsl = true,
        public readonly bool|array $allowRedirects = true,
        public readonly string|array|null $proxy = null,
    ) {
        if ($timeout <= 0.0) {
            throw new InvalidConfigurationException('ClientConfig timeout must be greater than zero.');
        }

        if ($connectTimeout <= 0.0) {
            throw new InvalidConfigurationException('ClientConfig connectTimeout must be greater than zero.');
        }
    }
}
