<?php

declare(strict_types=1);

namespace JOOservices\Client\Dto;

use JOOservices\Dto\Core\Dto;

final class RequestOptions extends Dto
{
    /**
     * @param string|array<string, mixed>|null $proxy
     * @param bool|array<string, mixed>|null $allowRedirects
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public readonly ?float $timeout = null,
        public readonly ?float $connectTimeout = null,
        public readonly string|array|null $proxy = null,
        public readonly ?bool $verifySsl = null,
        public readonly bool|array|null $allowRedirects = null,
        public readonly array $extra = [],
    ) {
    }
}
