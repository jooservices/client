<?php

declare(strict_types=1);

namespace JOOservices\Client\Client;

use JOOservices\Client\Contracts\MiddlewareInterface;
use JOOservices\Client\Contracts\TransportInterface;
use JOOservices\Client\Support\Psr17Bundle;

final readonly class ClientWiring
{
    /**
     * @param array<string, bool> $explicitCapabilities
     * @param array<string, MiddlewareInterface> $middlewares
     */
    public function __construct(
        public array $explicitCapabilities,
        public ?TransportInterface $transport,
        public ?Psr17Bundle $psr17,
        public array $middlewares,
    ) {
    }
}
