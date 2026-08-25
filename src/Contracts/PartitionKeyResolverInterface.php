<?php

declare(strict_types=1);

namespace JOOservices\Client\Contracts;

use Psr\Http\Message\RequestInterface;

interface PartitionKeyResolverInterface
{
    public function resolve(RequestInterface $request): string;
}
