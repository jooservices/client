<?php

declare(strict_types=1);

namespace JOOservices\Client\Support;

use JOOservices\Client\Contracts\PartitionKeyResolverInterface;
use Psr\Http\Message\RequestInterface;

final class HostPartitionKeyResolver implements PartitionKeyResolverInterface
{
    public function resolve(RequestInterface $request): string
    {
        $uri = $request->getUri();

        return strtolower($uri->getScheme() . '://' . $uri->getHost() . ':' . ($uri->getPort() ?? 0));
    }
}
