<?php

declare(strict_types=1);

namespace JOOservices\Client\Contracts;

interface TransportInterface extends RequestHandlerInterface
{
    public function capabilities(): TransportCapabilities;
}
